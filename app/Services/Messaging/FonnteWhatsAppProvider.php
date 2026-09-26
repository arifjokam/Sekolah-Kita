<?php

namespace App\Services\Messaging;

use App\Contracts\WhatsAppDeliveryResult;
use App\Contracts\WhatsAppProvider;
use App\Enums\NotificationDeliveryStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fonnte WhatsApp gateway adapter.
 *
 * Configured entirely from the environment (FONNTE_TOKEN, FONNTE_ENDPOINT). The
 * token is only ever read from configuration and is never logged, never included
 * in an exception message and never written to the delivery row.
 *
 * A non-2xx response, a transport error and a gateway-level rejection all resolve
 * to a WhatsAppDeliveryResult rather than an exception, so a failed message is
 * recorded as Failed and retried by the queue instead of aborting the request
 * that triggered it.
 */
class FonnteWhatsAppProvider implements WhatsAppProvider
{
    public function __construct(private readonly HttpFactory $http) {}

    public function name(): string
    {
        return 'fonnte';
    }

    public function isConfigured(): bool
    {
        return $this->token() !== null && $this->token() !== '';
    }

    public function unavailableReason(): ?string
    {
        if (! $this->isConfigured()) {
            return 'FONNTE_TOKEN belum diisi.';
        }

        return null;
    }

    public function send(string $recipient, string $message): WhatsAppDeliveryResult
    {
        if (! $this->isConfigured()) {
            return WhatsAppDeliveryResult::notConfigured(
                $this->name(),
                $this->unavailableReason() ?? 'Provider tidak dikonfigurasi.',
            );
        }

        $from = (string) config('sekolah-kita.whatsapp.from', '');

        try {
            $response = $this->http
                ->timeout((int) config('sekolah-kita.whatsapp.providers.fonnte.timeout', 15))
                ->withHeaders(['Authorization' => (string) $this->token()])
                ->asForm()
                ->post((string) config('sekolah-kita.whatsapp.providers.fonnte.endpoint'), array_filter([
                    'target' => $recipient,
                    'message' => $message,
                    'countryCode' => '62',
                    'from' => $from !== '' ? $from : null,
                ], static fn (?string $value): bool => $value !== null && $value !== ''));
        } catch (ConnectionException $exception) {
            Log::warning('whatsapp.transport_failure', [
                'provider' => $this->name(),
                'exception' => $exception::class,
            ]);

            return WhatsAppDeliveryResult::failed($this->name(), 'Koneksi ke gateway WhatsApp gagal.');
        } catch (Throwable $exception) {
            Log::error('whatsapp.unexpected_failure', [
                'provider' => $this->name(),
                'exception' => $exception::class,
            ]);

            return WhatsAppDeliveryResult::failed($this->name(), 'Gateway WhatsApp mengalami kesalahan tak terduga.');
        }

        if ($response->failed()) {
            return WhatsAppDeliveryResult::failed(
                $this->name(),
                'Gateway WhatsApp merespons HTTP '.$response->status().'.',
            );
        }

        $payload = $response->json();
        $reportedStatus = is_array($payload) ? strtoupper((string) ($payload['status'] ?? '')) : '';

        if ($reportedStatus !== '' && $reportedStatus !== 'SENT' && $reportedStatus !== 'PENDING') {
            $reason = is_array($payload) ? (string) ($payload['reason'] ?? 'Ditolak gateway.') : 'Ditolak gateway.';

            return new WhatsAppDeliveryResult(
                NotificationDeliveryStatus::Failed,
                $this->name(),
                'Gateway menolak: '.Str::limit($reason, 180, ''),
            );
        }

        $externalId = is_array($payload) && isset($payload['id']) && is_scalar($payload['id'])
            ? (string) $payload['id']
            : null;

        return WhatsAppDeliveryResult::sent($this->name(), $externalId);
    }

    private function token(): ?string
    {
        $token = config('sekolah-kita.whatsapp.providers.fonnte.token');

        return is_string($token) ? trim($token) : null;
    }
}

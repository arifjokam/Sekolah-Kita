<?php

namespace App\Services;

use App\Exceptions\AuditWriteException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class AuditLogService
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'remember_token',
        'token',
        'access_token',
        'secret',
        'api_key',
        'email',
        'phone',
        'address',
        'name',
        'birth_date',
        'birth_place',
        'national_id',
        'nisn',
        'student_number',
        'employee_number',
        'registration_number',
        'message',
        'body',
        'document',
        'document_path',
        'ip',
        'user_agent',
    ];

    /**
     * Record a redacted audit event.
     *
     * The context is deliberately metadata-only. Sensitive values are never
     * copied into the audit store, and failed writes do not expose database
     * exception messages to the caller.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(
        ?User $actor,
        string $action,
        string $targetType,
        int|string|null $targetId = null,
        string $result = 'success',
        array $context = [],
        ?string $reasonCode = null,
        ?Request $request = null,
    ): ?AuditLog {
        if (! in_array($result, ['success', 'denied', 'failed'], true)) {
            throw new InvalidArgumentException('Unsupported audit result.');
        }

        $requestContext = $this->requestContext($request);

        try {
            return AuditLog::query()->create([
                'actor_user_id' => $actor?->getKey(),
                'action' => $action,
                'result' => $result,
                'target_type' => $targetType,
                'target_id' => $targetId === null ? null : (string) $targetId,
                'changed_fields' => $this->redactContext($context),
                'reason_code' => $reasonCode === null ? null : Str::limit($reasonCode, 100, ''),
                'correlation_id' => $requestContext['correlation_id'],
                'ip_hash' => $requestContext['ip_hash'],
                'user_agent_hash' => $requestContext['user_agent_hash'],
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::critical('Audit write failed.', [
                'action' => $action,
                'target_type' => $targetType,
                'exception' => $exception::class,
            ]);

            if (config('sekolah-kita.audit.failure_mode') === 'throw') {
                throw new AuditWriteException(previous: $exception);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordOrFail(
        ?User $actor,
        string $action,
        string $targetType,
        int|string|null $targetId = null,
        string $result = 'success',
        array $context = [],
        ?string $reasonCode = null,
        ?Request $request = null,
    ): AuditLog {
        $record = $this->record($actor, $action, $targetType, $targetId, $result, $context, $reasonCode, $request);

        if ($record === null) {
            throw new AuditWriteException;
        }

        return $record;
    }

    /**
     * @return array<string, string|null>
     */
    public function requestContext(?Request $request): array
    {
        $requestId = $request?->header('X-Request-ID');

        return [
            'correlation_id' => is_string($requestId) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId) === 1
                ? strtolower($requestId)
                : (string) Str::uuid(),
            'ip_hash' => $this->hashNetworkValue($request?->ip()),
            'user_agent_hash' => $this->hashNetworkValue($request?->userAgent()),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function redactContext(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            $normalizedKey = Str::lower((string) $key);

            if ($this->isSensitiveKey($normalizedKey)) {
                $redacted[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = $this->redactNestedValue($value);

                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $redacted[$key] = $value;

                continue;
            }

            if (is_string($value) && preg_match('/^[A-Za-z0-9._:-]{1,80}$/', $value) === 1) {
                $redacted[$key] = $value;

                continue;
            }

            $redacted[$key] = '[omitted]';
        }

        return $redacted;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function redactNestedValue(array $values): array
    {
        $redacted = [];

        foreach ($values as $key => $value) {
            $normalizedKey = Str::lower((string) $key);

            $redacted[$key] = $this->isSensitiveKey($normalizedKey)
                ? '[redacted]'
                : (is_scalar($value) || $value === null ? '[metadata]' : '[omitted]');
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if (str_contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    private function hashNetworkValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}

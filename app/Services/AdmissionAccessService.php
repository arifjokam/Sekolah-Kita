<?php

namespace App\Services;

use App\Enums\AdmissionAccessTokenPurpose;
use App\Models\AdmissionApplication;
use App\Models\AdmissionApplicationAccessToken;
use Throwable;

class AdmissionAccessService
{
    public function issue(
        AdmissionApplication $application,
        AdmissionAccessTokenPurpose $purpose,
        ?int $ttlDays = null,
    ): string {
        $rawToken = bin2hex(random_bytes(32));
        $ttlDays ??= $purpose === AdmissionAccessTokenPurpose::Status
            ? (int) config('sekolah-kita.ppdb.access_token_ttl_days', 7)
            : 2;

        AdmissionApplicationAccessToken::query()->create([
            'admission_application_id' => $application->getKey(),
            'token_hash' => hash('sha256', $rawToken),
            'purpose' => $purpose->value,
            'expires_at' => now()->addDays($ttlDays),
        ]);

        return $rawToken;
    }

    public function resolve(string $rawToken, AdmissionAccessTokenPurpose $purpose): ?AdmissionApplication
    {
        if ($rawToken === '' || strlen($rawToken) > 128) {
            return null;
        }

        $accessToken = AdmissionApplicationAccessToken::query()
            ->with('application')
            ->where('token_hash', hash('sha256', $rawToken))
            ->where('purpose', $purpose->value)
            ->first();

        if ($accessToken === null || ! $accessToken->isUsable()) {
            return null;
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $accessToken->application;
    }

    public function revokeForApplication(AdmissionApplication $application, ?AdmissionAccessTokenPurpose $purpose = null): void
    {
        AdmissionApplicationAccessToken::query()
            ->where('admission_application_id', $application->getKey())
            ->when($purpose !== null, fn ($query) => $query->where('purpose', $purpose->value))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function rotate(
        AdmissionApplication $application,
        AdmissionAccessTokenPurpose $purpose,
        ?int $ttlDays = null,
    ): string {
        try {
            $this->revokeForApplication($application, $purpose);

            return $this->issue($application, $purpose, $ttlDays);
        } catch (Throwable $exception) {
            report($exception);

            throw $exception;
        }
    }
}

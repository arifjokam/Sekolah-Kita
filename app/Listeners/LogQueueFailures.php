<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Terminal queue failures are already persisted to the `failed_jobs` table by
 * the framework. This listener adds a structured, secret-free log line so the
 * failure is visible in the log stream that operators already monitor instead
 * of being discoverable only by querying the database.
 *
 * Payloads are never logged: queue job payloads can contain PII, private
 * object keys, or access-token material.
 */
class LogQueueFailures
{
    public function handle(JobFailed $event): void
    {
        $job = $event->job;

        Log::error('queue.job_failed', [
            'connection' => $event->connectionName,
            'queue' => $this->stringOrNull($job->getQueue()),
            'job_name' => $this->stringOrNull($job->resolveName()),
            'job_id' => $this->stringOrNull($job->getJobId()),
            'attempts' => $this->attemptCount($job),
            'exception' => $event->exception::class,
        ]);
    }

    /**
     * The attempt count comes from the job contract rather than the raw
     * payload so the serialized job body is never read here.
     */
    private function attemptCount(mixed $job): ?int
    {
        try {
            $attempts = $job->attempts();

            return is_int($attempts) ? $attempts : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class AuditMaintenanceCommand extends Command
{
    protected $signature = 'audit:maintenance {--execute : Acknowledge the reviewed retention procedure}';

    protected $description = 'Report audit retention candidates without deleting append-only records.';

    public function handle(): int
    {
        $retentionDays = config('sekolah-kita.audit.retention_days');

        if ($retentionDays === null || (int) $retentionDays < 1) {
            $this->warn('Audit retention is not configured; no records were changed.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays((int) $retentionDays);
        $count = AuditLog::query()->where('occurred_at', '<', $cutoff)->count();

        $this->line('Audit records older than the configured retention boundary: '.$count.'.');
        $this->warn('Audit records are append-only. Deletion requires a separately approved, hold-aware retention procedure.');

        if ($this->option('execute')) {
            $this->warn('No audit records were deleted by this command.');
        }

        return self::SUCCESS;
    }
}

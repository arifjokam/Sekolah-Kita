<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Records proof that the operating-system scheduler is still firing.
 *
 * A misconfigured or stopped cron entry fails silently: no error surfaces and
 * cache warming, audit reporting, and queue monitoring simply stop happening.
 * This command writes a timestamp that monitoring can alert on, and it is the
 * evidence a go/no-go record needs to show the scheduler is running.
 */
class PlatformHeartbeatCommand extends Command
{
    protected $signature = 'platform:heartbeat';

    protected $description = 'Record that the scheduler is running so a stopped cron entry is observable.';

    /**
     * The heartbeat key is an operational signal, not business data, so it is
     * kept on a short TTL. A missing key therefore means "no successful run
     * within the last hour" rather than an ever-growing cache entry.
     */
    private const CACHE_KEY = 'platform:scheduler:last-run';

    public function handle(): int
    {
        Cache::put(self::CACHE_KEY, now()->toIso8601String(), now()->addHour());

        $this->line('Scheduler heartbeat recorded.');

        return self::SUCCESS;
    }
}

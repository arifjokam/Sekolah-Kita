<?php

namespace App\Console\Commands;

use App\Jobs\WarmPublicContentCache;
use App\Services\PublicContentReader;
use Illuminate\Console\Command;

class WarmPublicContentCacheCommand extends Command
{
    protected $signature = 'platform:warm-cache {--sync : Run the warmer in the current process}';

    protected $description = 'Warm the bounded public content cache projections.';

    public function handle(PublicContentReader $content): int
    {
        if ($this->option('sync')) {
            (new WarmPublicContentCache)->handle($content);
            $this->info('Public content cache warmed synchronously.');

            return self::SUCCESS;
        }

        WarmPublicContentCache::dispatch();
        $this->info('Public content cache warmer dispatched.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\ProductionReadinessService;
use Illuminate\Console\Command;

class ProductionReadinessCommand extends Command
{
    protected $signature = 'platform:readiness {--json : Emit machine-readable JSON}';

    protected $description = 'Check production configuration and infrastructure prerequisites without exposing secrets.';

    public function handle(ProductionReadinessService $readiness): int
    {
        $result = $readiness->check();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($result['checks'] as $check) {
                $this->line(($check['ok'] ? '[PASS]' : '[FAIL]').' '.$check['name'].': '.$check['message']);
            }
        }

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}

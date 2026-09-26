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

            return $result['ready'] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($result['groups'] as $group => $checks) {
            $this->newLine();
            $this->line('<options=bold>'.$group.'</>');

            foreach ($checks as $check) {
                $label = $check['ok']
                    ? '[PASS]'
                    : ($check['blocking'] ? '[FAIL]' : '[WARN]');

                $this->line('  '.$label.' '.$check['name'].': '.$check['message']);
            }
        }

        $blocking = array_values(array_filter(
            $result['checks'],
            static fn (array $check): bool => ! $check['ok'] && $check['blocking'],
        ));

        $this->newLine();
        $this->line($result['ready']
            ? '<info>READY: no blocking production prerequisite is missing.</>'
            : '<error>NOT READY: '.count($blocking).' blocking prerequisite(s) failed.</>');

        return $result['ready'] ? self::SUCCESS : self::FAILURE;
    }
}

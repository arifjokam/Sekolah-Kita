<?php

namespace App\Console\Commands;

use Database\Seeders\DemoUatSeeder;
use Database\Seeders\DevelopmentAccountsSeeder;
use Illuminate\Console\Command;

/**
 * Provisions the local UAT dataset.
 *
 * The command is the only entry point to the demo data, and it refuses to run
 * in production: a school deployment must never contain fixture accounts or
 * fixture admissions, and the accounts themselves use a password that is public
 * in this repository.
 */
class SeedDemoDataCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sekolah:seed-demo {--force : Skip the interactive confirmation}';

    /**
     * @var string
     */
    protected $description = 'Seed the local development UAT dataset (users, academic masters, PPDB and sample content).';

    public function handle(): int
    {
        // The production guard has no bypass flag. --force only skips the
        // interactive confirmation, because a school deployment must never be
        // able to opt into fixture accounts even by accident.
        if (app()->environment('production')) {
            $this->components->error('The demo dataset cannot be seeded in production.');

            return self::FAILURE;
        }

        if (! $this->confirmToProceed()) {
            return self::SUCCESS;
        }

        $this->components->info('Seeding the Sekolah Kita development dataset.');

        $this->call('db:seed', ['--class' => DemoUatSeeder::class, '--force' => true]);

        $this->newLine();
        $this->components->twoColumnDetail('Administrator', 'admin.test@sekolah.local');
        $this->components->twoColumnDetail('Guru', 'guru.test@sekolah.local');
        $this->components->twoColumnDetail('Siswa', 'siswa.test@sekolah.local');
        $this->components->twoColumnDetail('Password (dev only)', DevelopmentAccountsSeeder::PASSWORD);

        return self::SUCCESS;
    }

    private function confirmToProceed(): bool
    {
        if ($this->option('force') || ! $this->input->isInteractive()) {
            return true;
        }

        return $this->components->confirm(
            'This writes fixture data into the '.app()->environment().' database. Continue?',
            true,
        );
    }
}

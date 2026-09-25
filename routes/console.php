<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('platform:warm-cache')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('audit:maintenance')
    ->dailyAt('02:00')
    ->withoutOverlapping(30)
    ->onOneServer();

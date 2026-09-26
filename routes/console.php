<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$queues = (array) config('sekolah-kita.queue.queues', []);

/*
|--------------------------------------------------------------------------
| Cache warming
|--------------------------------------------------------------------------
|
| The warmer is a ShouldBeUnique job, so the queue itself already dedupes
| concurrent dispatches. withoutOverlapping() additionally stops a second
| scheduler tick from starting the command while the previous run is still
| warming, and onOneServer() keeps a single node responsible when the
| scheduler is running on more than one host.
|
| The dispatch must never run inline in the web request path: the warming
| projection reads every published public record.
|
*/

Schedule::command('platform:warm-cache')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Audit retention reporting
|--------------------------------------------------------------------------
|
| Report only. This command never deletes audit history; retention requires a
| separately approved, hold-aware procedure.
|
*/

Schedule::command('audit:maintenance')
    ->dailyAt('02:00')
    ->withoutOverlapping(30)
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Queue depth monitoring
|--------------------------------------------------------------------------
|
| queue:monitor dispatches an event when the backlog exceeds the configured
| threshold, which turns "the queue is backing up" into something an alerting
| rule can observe. It is cheap: it reads the queue size, not the jobs.
|
*/

if ($queues !== []) {
    Schedule::command('queue:monitor', array_merge($queues, [
        '--max' => (int) config('sekolah-kita.queue.monitor_threshold', 100),
    ]))
        ->everyFiveMinutes()
        ->withoutOverlapping(5)
        ->onOneServer();
}

/*
|--------------------------------------------------------------------------
| Failed job retention
|--------------------------------------------------------------------------
|
| Pruning removes failure evidence, so it only runs when an operator has
| explicitly set a retention window. With no window configured the table grows
| until someone looks at it, which is the safer default for an append-only
| failure record.
|
*/

$pruneFailedAfterDays = config('sekolah-kita.queue.prune_failed_after_days');

if ($pruneFailedAfterDays !== null && (int) $pruneFailedAfterDays > 0) {
    Schedule::command('queue:prune-failed', ['--hours' => (int) $pruneFailedAfterDays * 24])
        ->dailyAt('03:30')
        ->withoutOverlapping(30)
        ->onOneServer();
}

/*
|--------------------------------------------------------------------------
| Scheduler heartbeat
|--------------------------------------------------------------------------
|
| A broken cron entry is otherwise silent. The heartbeat writes a timestamp
| that operations can alert on, and it is the evidence the go/no-go record
| needs to show the scheduler is actually running.
|
*/

/*
| Abandoned article body images
|
| An image uploaded from the article create screen is published as soon as it is
| uploaded, so an abandoned draft would otherwise leave a public file behind
| forever. The command only ever discards assets that no other reference points
| at.
|
*/

Schedule::command('news:prune-staged-article-images')
    ->hourly()
    ->withoutOverlapping(15)
    ->onOneServer();

Schedule::command('platform:heartbeat')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->onOneServer();

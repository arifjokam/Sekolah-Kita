<?php

namespace App\Jobs;

use App\Services\PublicContentReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class WarmPublicContentCache implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct()
    {
        $this->onQueue('maintenance');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'public-content-cache-v1';
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(PublicContentReader $content): void
    {
        $content->schoolProfile();
        $content->homepageNews();
        $content->homepageAchievements();
        $content->homepageGalleryAlbums();
        $content->news(1);
        $content->achievements(1);
        $content->galleryAlbums(1);
        $content->teachers(1);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Public content cache warming failed.', [
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}

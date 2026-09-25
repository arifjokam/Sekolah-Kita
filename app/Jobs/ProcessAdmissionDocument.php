<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use App\Services\PrivateDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAdmissionDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $mediaAssetId)
    {
        $this->onQueue('media');
        $this->afterCommit();
    }

    public function handle(PrivateDocumentService $documents): void
    {
        $mediaAsset = MediaAsset::query()->findOrFail($this->mediaAssetId);
        $documents->inspect($mediaAsset);
    }

    public function failed(?Throwable $exception): void
    {
        $mediaAsset = MediaAsset::query()->find($this->mediaAssetId);

        if ($mediaAsset !== null) {
            app(PrivateDocumentService::class)->markFailed($mediaAsset, 'processing_failed');
        }

        Log::error('Admission document processing failed.', [
            'media_asset_id' => $this->mediaAssetId,
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }
}

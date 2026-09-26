<?php

namespace App\Console\Commands;

use App\Enums\MediaProcessingStatus;
use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\NewsArticleMedia;
use App\Services\MediaAssetService;
use App\Services\NewsArticleBodyImageService;
use Illuminate\Console\Command;

/**
 * Removes article body images that were uploaded but never ended up in an
 * article.
 *
 * An image is published as soon as it is uploaded, because the body stores its
 * public URL and every publicization mints a new object key. Two situations
 * leave such an image behind forever without this command:
 *
 *  - the create screen uploads before the article row exists, so the image is
 *    recorded as a staged row without an article;
 *  - the edit screen uploads, but the author removes the image again or simply
 *    abandons the form before saving.
 *
 * The second case is resolved by replaying the normal reference reconciliation
 * for the article, which is exactly the same code path a save performs.
 * MediaAssetService::discard() is a no-op while any other reference exists, so
 * an image that is still in use is never touched.
 */
class PruneStagedArticleImagesCommand extends Command
{
    protected $signature = 'news:prune-staged-article-images {--hours= : Override the retention window in hours}';

    protected $description = 'Discard article body images that were uploaded but never saved into an article body.';

    public function handle(MediaAssetService $mediaAssets, NewsArticleBodyImageService $images): int
    {
        $hours = (int) ($this->option('hours') ?: config('sekolah-kita.news.staged_image_ttl_hours', 6));

        if ($hours < 1) {
            $this->warn('The staged image retention window must be at least one hour; nothing was changed.');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours($hours);

        $discarded = $this->discardAbandonedStagedImages($mediaAssets, $cutoff);
        $reconciled = $this->reconcileStaleArticles($images, $cutoff);

        $this->line(sprintf(
            'Discarded %d abandoned article image(s) and reconciled %d stale article(s), uploaded before %s.',
            $discarded,
            $reconciled,
            $cutoff->toDateTimeString(),
        ));

        return self::SUCCESS;
    }

    private function discardAbandonedStagedImages(MediaAssetService $mediaAssets, mixed $cutoff): int
    {
        $candidates = NewsArticleMedia::query()
            ->whereNull('news_article_id')
            ->where('created_at', '<', $cutoff)
            ->pluck('media_asset_id')
            ->unique();

        $discarded = 0;

        foreach ($candidates as $mediaId) {
            $media = MediaAsset::query()->find($mediaId);

            // The reference row has to go first: MediaAssetService refuses to
            // discard an asset that is still referenced anywhere.
            NewsArticleMedia::query()->where('media_asset_id', $mediaId)->delete();

            if ($media === null) {
                continue;
            }

            $mediaAssets->discard($media);

            // discard() is a no-op for an asset another reference still points
            // at, so the counter only counts real deletions.
            if ($media->refresh()->processing_status === MediaProcessingStatus::Deleted) {
                $discarded++;
            }
        }

        return $discarded;
    }

    private function reconcileStaleArticles(NewsArticleBodyImageService $images, mixed $cutoff): int
    {
        $articleIds = NewsArticleMedia::query()
            ->whereNotNull('news_article_id')
            ->where('created_at', '<', $cutoff)
            ->pluck('news_article_id')
            ->unique();

        $reconciled = 0;

        foreach ($articleIds as $articleId) {
            $article = NewsArticle::query()->find($articleId);

            if ($article === null) {
                NewsArticleMedia::query()->where('news_article_id', $articleId)->delete();

                continue;
            }

            $images->synchronizeReferences($article);
            $reconciled++;
        }

        return $reconciled;
    }
}

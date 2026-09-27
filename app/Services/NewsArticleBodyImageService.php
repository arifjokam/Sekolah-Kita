<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\NewsArticleMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Owns the images that live inside a news article body.
 *
 * Images are never inlined into the markup. The editor uploads them here,
 * MediaAssetService decodes and re-encodes them, and only the resulting public
 * media URL is written into the body. The news_article_media table records which
 * article each image belongs to, which is what lets MediaAssetService know that
 * a public derivative is still referenced and when it may be withdrawn.
 */
final class NewsArticleBodyImageService
{
    public function __construct(
        private readonly MediaAssetService $mediaAssets,
        private readonly RichTextSanitizer $sanitizer,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Store an image uploaded from the article editor.
     *
     * @return array{id: int, url: string, width: int|null, height: int|null, mime_type: string, max_bytes: int}
     */
    public function upload(User $actor, ?NewsArticle $article, UploadedFile $file): array
    {
        Gate::forUser($actor)->authorize('uploadBodyImage', $article ?? NewsArticle::class);

        $media = $this->mediaAssets->store($file, $actor);
        NewsArticleMedia::query()->create([
            'news_article_id' => $article?->getKey(),
            'media_asset_id' => $media->getKey(),
        ]);

        // The reference row above is what makes the derivative public. The body
        // stores the resulting URL, and every publicization mints a new object
        // key, so the derivative must exist before the editor inserts the URL.
        $this->mediaAssets->synchronize($media);
        $media->refresh();

        $url = $this->mediaAssets->publicUrl($media);

        if ($url === null) {
            NewsArticleMedia::query()->where('media_asset_id', $media->getKey())->delete();
            $this->mediaAssets->discard($media);

            throw new RuntimeException('The uploaded image could not be published for the article body.');
        }

        $this->audit->record(
            actor: $actor,
            action: 'news_body_image.uploaded',
            targetType: 'media_asset',
            targetId: $media->getKey(),
            context: ['content_type' => 'news_body_image'],
        );

        return [
            'id' => (int) $media->getKey(),
            'url' => $url,
            'width' => $media->width,
            'height' => $media->height,
            'mime_type' => (string) $media->mime_type,
            'max_bytes' => MediaAssetService::MAX_IMAGE_BYTES,
        ];
    }

    /**
     * Sanitize a submitted article body against the images this editor session
     * is allowed to reference.
     *
     * A body that is not rich text is passed through untouched so articles
     * written before the editor existed keep rendering exactly as they did.
     */
    public function prepareBody(User $actor, ?NewsArticle $article, mixed $body): ?string
    {
        if (! is_string($body) || ! RichTextSanitizer::looksLikeRichText($body)) {
            return is_string($body) ? $body : null;
        }

        if (strlen($body) > RichTextSanitizer::MAX_BODY_BYTES) {
            throw ValidationException::withMessages([
                'body' => 'Isi berita melebihi kapasitas penyimpanan. Ringkas isi berita.',
            ]);
        }

        return $this->sanitizer->sanitize($body, $this->imagePolicy($actor, $article));
    }

    /**
     * Reconcile the recorded image references with the body that was stored.
     *
     * Images the body no longer references lose their reference, which is the
     * only moment their public derivative may be withdrawn. Images the body does
     * reference are attached to the article, adopting any row staged by the
     * create screen.
     */
    public function synchronizeReferences(NewsArticle $article): void
    {
        $referencedIds = $this->referencedMediaIds($article->body);

        $rows = NewsArticleMedia::query()
            ->where('news_article_id', $article->getKey())
            ->with('mediaAsset')
            ->get();

        foreach ($rows as $row) {
            if (in_array((int) $row->media_asset_id, $referencedIds, true)) {
                continue;
            }

            $row->delete();

            if ($row->mediaAsset !== null) {
                $this->mediaAssets->synchronize($row->mediaAsset);
            }
        }

        foreach ($referencedIds as $mediaId) {
            NewsArticleMedia::query()->updateOrCreate([
                'news_article_id' => $article->getKey(),
                'media_asset_id' => $mediaId,
            ]);

            NewsArticleMedia::query()
                ->whereNull('news_article_id')
                ->where('media_asset_id', $mediaId)
                ->delete();
        }
    }

    /**
     * The object keys this editor session may reference: the images already
     * attached to the article, plus the images this actor staged on the create
     * screen before the article existed.
     *
     * The two scopes are resolved separately on purpose. Folding them into one
     * disjunction inside a whereHas callback would rely on how a leading
     * boolean is compiled, and a mistake there would silently widen the
     * allowlist to every image of the actor.
     */
    private function imagePolicy(User $actor, ?NewsArticle $article): RichTextImageSourcePolicy
    {
        $attached = $article === null
            ? collect()
            : NewsArticleMedia::query()
                ->where('news_article_id', $article->getKey())
                ->pluck('media_asset_id');

        $staged = NewsArticleMedia::query()
            ->whereNull('news_article_id')
            ->whereHas('mediaAsset', fn (Builder $media) => $media
                ->where('uploader_user_id', $actor->getKey()))
            ->pluck('media_asset_id');

        $objectKeys = MediaAsset::query()
            ->where('public_disk', 'public')
            ->whereNotNull('public_object_key')
            ->whereNull('deleted_at')
            ->whereIn('id', $attached->merge($staged)->unique()->values()->all())
            ->pluck('public_object_key')
            ->all();

        return RichTextImageSourcePolicy::onlyObjectKeys($objectKeys);
    }

    /**
     * The media assets a stored body still points at.
     *
     * @return list<int>
     */
    private function referencedMediaIds(?string $body): array
    {
        if ($body === null || ! RichTextSanitizer::looksLikeRichText($body)) {
            return [];
        }

        preg_match_all('#<img\b[^>]*\bsrc="([^"]+)"#i', $body, $matches);

        $objectKeys = array_values(array_filter(array_map(
            static fn (string $source): ?string => RichTextImageSourcePolicy::objectKeyFrom($source),
            $matches[1] ?? [],
        )));

        if ($objectKeys === []) {
            return [];
        }

        return MediaAsset::query()
            ->where('public_disk', 'public')
            ->whereNotNull('public_object_key')
            ->whereNull('deleted_at')
            ->whereIn('public_object_key', $objectKeys)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }
}

<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\AdmissionPeriod;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\SchoolProfile;
use App\Models\Teacher;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class PublicContentReader
{
    public function __construct(
        private readonly MediaAssetService $mediaAssets,
        private readonly PublicContentCache $cache,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function schoolProfile(): ?array
    {
        return $this->cache->remember('school-profile', 'singleton', function (): ?array {
            $profile = SchoolProfile::query()
                ->where('is_singleton', true)
                ->select([
                    'id',
                    'legal_name',
                    'public_name',
                    'short_name',
                    'address_line',
                    'city',
                    'postal_code',
                    'public_phone',
                    'public_email',
                    'vision',
                    'mission',
                    'about',
                    'logo_media_id',
                    'hero_media_id',
                ])
                ->with([
                    'logoMedia' => $this->publicMediaRelation(),
                    'heroMedia' => $this->publicMediaRelation(),
                ])
                ->first();

            if ($profile === null) {
                return null;
            }

            return [
                'name' => $profile->displayName(),
                'legal_name' => $profile->legal_name,
                'short_name' => $profile->short_name,
                'address_line' => $profile->address_line,
                'city' => $profile->city,
                'postal_code' => $profile->postal_code,
                'public_phone' => $profile->public_phone,
                'public_email' => $profile->public_email,
                'vision' => $profile->vision,
                'mission' => $profile->mission,
                'about' => $profile->about,
                'logo' => $this->mapMedia($profile->logoMedia),
                'hero' => $this->mapMedia($profile->heroMedia),
            ];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function homepageNews(int $limit = 3): array
    {
        return $this->cache->remember('news', 'home:'.$limit, function () use ($limit): array {
            return NewsArticle::query()
                ->published()
                ->select(['id', 'title', 'slug', 'excerpt', 'published_at', 'cover_media_id'])
                ->with(['coverMedia' => $this->publicMediaRelation()])
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (NewsArticle $article): array => $this->mapNews($article))
                ->all();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function homepageAchievements(int $limit = 3): array
    {
        return $this->cache->remember('achievements', 'home:'.$limit, function () use ($limit): array {
            return Achievement::query()
                ->published()
                ->select(['id', 'title', 'slug', 'description', 'category', 'level', 'achievement_date', 'cover_media_id'])
                ->with(['coverMedia' => $this->publicMediaRelation()])
                ->orderByDesc('achievement_date')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (Achievement $achievement): array => $this->mapAchievement($achievement))
                ->all();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function homepageGalleryAlbums(int $limit = 3): array
    {
        return $this->cache->remember('gallery', 'albums:home:'.$limit, function () use ($limit): array {
            return GalleryAlbum::query()
                ->published()
                ->select(['id', 'title', 'slug', 'description', 'published_at'])
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (GalleryAlbum $album): array => $this->mapGalleryAlbum($album))
                ->all();
        });
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function news(int $page, int $perPage = 9): LengthAwarePaginator
    {
        return $this->paginate(
            NewsArticle::query()
                ->published()
                ->select(['id', 'title', 'slug', 'excerpt', 'published_at', 'cover_media_id'])
                ->with(['coverMedia' => $this->publicMediaRelation()])
                ->orderByDesc('published_at')
                ->orderByDesc('id'),
            $page,
            $perPage,
            fn (NewsArticle $article): array => $this->mapNews($article),
            'news',
            'list:'.$perPage,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function newsArticle(string $slug): ?array
    {
        return $this->cache->remember('news', 'detail:'.$slug, function () use ($slug): ?array {
            $article = NewsArticle::query()
                ->published()
                ->where('slug', $slug)
                ->select([
                    'id',
                    'title',
                    'slug',
                    'excerpt',
                    'body',
                    'published_at',
                    'cover_media_id',
                ])
                ->with(['coverMedia' => $this->publicMediaRelation()])
                ->first();

            return $article === null ? null : $this->mapNews($article);
        });
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function achievements(int $page, int $perPage = 9): LengthAwarePaginator
    {
        return $this->paginate(
            Achievement::query()
                ->published()
                ->select([
                    'id',
                    'title',
                    'slug',
                    'description',
                    'category',
                    'level',
                    'achievement_date',
                    'cover_media_id',
                ])
                ->with(['coverMedia' => $this->publicMediaRelation()])
                ->orderByDesc('achievement_date')
                ->orderByDesc('id'),
            $page,
            $perPage,
            fn (Achievement $achievement): array => $this->mapAchievement($achievement),
            'achievements',
            'list:'.$perPage,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function achievement(string $slug): ?array
    {
        return $this->cache->remember('achievements', 'detail:'.$slug, function () use ($slug): ?array {
            $achievement = Achievement::query()
                ->published()
                ->where('slug', $slug)
                ->select([
                    'id',
                    'title',
                    'slug',
                    'description',
                    'category',
                    'level',
                    'achievement_date',
                    'cover_media_id',
                ])
                ->with(['coverMedia' => $this->publicMediaRelation()])
                ->first();

            return $achievement === null ? null : $this->mapAchievement($achievement);
        });
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function galleryAlbums(int $page, int $perPage = 12): LengthAwarePaginator
    {
        return $this->paginate(
            GalleryAlbum::query()
                ->published()
                ->select(['id', 'title', 'slug', 'description', 'published_at'])
                ->orderBy('sort_order')
                ->orderByDesc('id'),
            $page,
            $perPage,
            fn (GalleryAlbum $album): array => $this->mapGalleryAlbum($album),
            'gallery',
            'albums:list:'.$perPage,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function galleryAlbum(string $slug): ?array
    {
        return $this->cache->remember('gallery', 'album:'.$slug, function () use ($slug): ?array {
            $album = GalleryAlbum::query()
                ->published()
                ->where('slug', $slug)
                ->select(['id', 'title', 'slug', 'description', 'published_at'])
                ->first();

            return $album === null ? null : $this->mapGalleryAlbum($album);
        });
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function galleryItems(string $albumSlug, int $page, int $perPage = 18): LengthAwarePaginator
    {
        return $this->paginate(
            GalleryItem::query()
                ->published()
                ->whereHas('galleryAlbum', fn (Builder $query) => $query
                    ->published()
                    ->where('slug', $albumSlug))
                ->whereHas('mediaAsset', fn (Builder $query) => $query->publicReady())
                ->select([
                    'id',
                    'gallery_album_id',
                    'media_asset_id',
                    'caption',
                    'alt_text',
                    'event_date',
                ])
                ->with(['mediaAsset' => $this->publicMediaRelation()])
                ->orderBy('sort_order')
                ->orderBy('id'),
            $page,
            $perPage,
            fn (GalleryItem $item): array => $this->mapGalleryItem($item),
            'gallery',
            'items:'.$albumSlug.':'.$perPage,
        );
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function teachers(int $page, int $perPage = 18): LengthAwarePaginator
    {
        return $this->paginate(
            Teacher::query()
                ->publiclyVisible()
                ->select([
                    'id',
                    'directory_slug',
                    'legal_name',
                    'position_title',
                    'biography',
                    'photo_media_id',
                    'published_at',
                ])
                ->with(['photoMedia' => $this->publicMediaRelation()])
                ->orderBy('legal_name')
                ->orderBy('id'),
            $page,
            $perPage,
            fn (Teacher $teacher): array => $this->mapTeacher($teacher),
            'teachers',
            'list:'.$perPage,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function teacher(string $slug): ?array
    {
        return $this->cache->remember('teachers', 'detail:'.$slug, function () use ($slug): ?array {
            $teacher = Teacher::query()
                ->publiclyVisible()
                ->where('directory_slug', $slug)
                ->select([
                    'id',
                    'directory_slug',
                    'legal_name',
                    'position_title',
                    'biography',
                    'photo_media_id',
                    'published_at',
                ])
                ->with(['photoMedia' => $this->publicMediaRelation()])
                ->first();

            return $teacher === null ? null : $this->mapTeacher($teacher);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function admissionPeriods(): array
    {
        return $this->cache->remember('ppdb', 'periods', function (): array {
            return AdmissionPeriod::query()
                ->published()
                ->select([
                    'id',
                    'name',
                    'slug',
                    'description',
                    'instructions',
                    'opens_at',
                    'closes_at',
                    'status',
                    'published_at',
                ])
                ->orderByDesc('opens_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (AdmissionPeriod $period): array => $this->mapAdmissionPeriod($period))
                ->all();
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function openAdmissionPeriod(): ?array
    {
        return $this->cache->remember('ppdb', 'open-period', function (): ?array {
            $period = AdmissionPeriod::query()
                ->open()
                ->select([
                    'id',
                    'name',
                    'slug',
                    'description',
                    'instructions',
                    'opens_at',
                    'closes_at',
                    'status',
                    'published_at',
                ])
                ->orderByDesc('opens_at')
                ->first();

            return $period === null ? null : $this->mapAdmissionPeriod($period);
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  Closure(TModel): array<string, mixed>  $mapper
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginate(
        Builder $query,
        int $page,
        int $perPage,
        Closure $mapper,
        string $group,
        string $key,
    ): LengthAwarePaginator {
        $page = max(1, min($page, 10_000));
        $perPage = max(1, min($perPage, 100));
        $payload = $this->cache->remember($group, $key.':page:'.$page, function () use ($query, $perPage, $page, $mapper): array {
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'items' => $paginator->getCollection()
                    ->map($mapper)
                    ->values()
                    ->all(),
                'total' => $paginator->total(),
            ];
        });

        return new LengthAwarePaginator(
            $payload['items'],
            $payload['total'],
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );
    }

    /**
     * @return Closure(Relation<MediaAsset>): void
     */
    private function publicMediaRelation(): Closure
    {
        return function (Relation $media): void {
            $media->where('processing_status', 'ready')
                ->where('scan_status', 'clean')
                ->where('visibility', 'public')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->select([
                    'id',
                    'media_kind',
                    'public_disk',
                    'public_object_key',
                    'mime_type',
                    'width',
                    'height',
                    'processing_status',
                    'scan_status',
                    'visibility',
                    'metadata_sanitized_at',
                    'published_at',
                    'deleted_at',
                ]);
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAdmissionPeriod(AdmissionPeriod $period): array
    {
        return [
            'id' => $period->getKey(),
            'name' => $period->name,
            'slug' => $period->slug,
            'description' => $period->description,
            'instructions' => $period->instructions,
            'opens_at' => $period->opens_at?->toIso8601String(),
            'closes_at' => $period->closes_at?->toIso8601String(),
            'opens_at_label' => $this->dateTimeLabel($period->opens_at),
            'closes_at_label' => $this->dateTimeLabel($period->closes_at),
            'status' => $period->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapNews(NewsArticle $article): array
    {
        return [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $article->body ?? null,
            'published_at' => $article->published_at?->toIso8601String(),
            'published_at_label' => $this->dateTimeLabel($article->published_at),
            'cover' => $this->mapMedia($article->coverMedia),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAchievement(Achievement $achievement): array
    {
        return [
            'title' => $achievement->title,
            'slug' => $achievement->slug,
            'description' => $achievement->description,
            'category' => $achievement->category,
            'level' => $achievement->level,
            'achievement_date' => $achievement->achievement_date?->format('Y-m-d'),
            'achievement_date_label' => $this->dateLabel($achievement->achievement_date),
            'cover' => $this->mapMedia($achievement->coverMedia),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapGalleryAlbum(GalleryAlbum $album): array
    {
        return [
            'title' => $album->title,
            'slug' => $album->slug,
            'description' => $album->description,
            'published_at' => $album->published_at?->toIso8601String(),
            'published_at_label' => $this->dateTimeLabel($album->published_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapGalleryItem(GalleryItem $item): array
    {
        return [
            'caption' => $item->caption,
            'alt_text' => $item->alt_text,
            'event_date' => $item->event_date?->format('Y-m-d'),
            'event_date_label' => $this->dateLabel($item->event_date),
            'image' => $this->mapMedia($item->mediaAsset),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTeacher(Teacher $teacher): array
    {
        return [
            'name' => $teacher->legal_name,
            'slug' => $teacher->directory_slug,
            'position_title' => $teacher->position_title,
            'biography' => $teacher->biography,
            'photo' => $this->mapMedia($teacher->photoMedia),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapMedia(?MediaAsset $media): ?array
    {
        $url = $this->mediaAssets->publicUrl($media);

        if ($media === null || $url === null) {
            return null;
        }

        return [
            'url' => $url,
            'mime_type' => $media->mime_type,
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    private function dateTimeLabel(?\DateTimeInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return Carbon::instance($date)
            ->setTimezone('Asia/Jakarta')
            ->format('d/m/Y H:i');
    }

    private function dateLabel(?\DateTimeInterface $date): ?string
    {
        return $date === null ? null : $date->format('d/m/Y');
    }
}

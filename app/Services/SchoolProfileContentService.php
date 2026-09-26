<?php

namespace App\Services;

use App\Models\SchoolProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Writes the long-form public copy on the school profile.
 *
 * Vision, mission and about are genuine long-form HTML fields: they are read on
 * the public site, and an administrator writing them needs paragraphs, lists and
 * emphasis rather than a single line. They are prepared here with the same
 * RichTextSanitizer the news body uses, so the stored markup is inside the same
 * allowlist and is sanitized again on read by PublicContentReader.
 *
 * Images are a different question from news body images. News body images get a
 * dedicated editor, a staged-media model and per-article ownership rules, all of
 * which are owned by NewsArticleBodyImageService. Reusing that machinery for the
 * profile would mean inventing staged media with no article to stage it against,
 * so the editor is mounted here without an image button. The profile keeps its
 * existing logo and hero file fields, which already go through MediaAssetService
 * with the same MIME, size and dimension limits.
 */
final class SchoolProfileContentService
{
    public function __construct(
        private readonly RichTextSanitizer $sanitizer,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function prepare(array $data): array
    {
        $prepared = [];

        foreach (['vision', 'mission', 'about'] as $field) {
            $value = $data[$field] ?? null;

            if (! is_string($value)) {
                continue;
            }

            if (strlen($value) > RichTextSanitizer::MAX_BODY_BYTES) {
                throw ValidationException::withMessages([
                    $field => 'Konten profil melebihi kapasitas penyimpanan. Ringkas isinya.',
                ]);
            }

            // Plain text is stored as plain text: a profile written before the
            // editor existed must keep rendering exactly as it did. Rich text is
            // reduced to the editor allowlist, and no image source is permitted
            // because the profile editor has no image button and no staged media
            // model. PublicContentReader sanitizes again on read.
            $prepared[$field] = RichTextSanitizer::looksLikeRichText($value)
                ? $this->sanitizer->sanitize($value, RichTextImageSourcePolicy::denyAll())
                : $value;
        }

        return $prepared;
    }

    /**
     * Persist the prepared copy together with the caller's own media handling.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files
     */
    public function save(
        User $actor,
        ?SchoolProfile $profile,
        array $data,
        ?UploadedFile $logo = null,
        ?UploadedFile $hero = null,
    ): SchoolProfile {
        $old = $profile === null
            ? null
            : $profile->only(['vision', 'mission', 'about']);

        $copy = $this->prepare($data);

        // The caller owns the row lifecycle and the media columns; this service
        // only decides what the long-form fields are allowed to contain. A
        // transaction keeps the audit entry and the write together.
        return DB::transaction(function () use ($actor, $profile, $data, $copy, $logo, $hero): SchoolProfile {
            $payload = [...$copy];

            $target = $profile;

            if ($target === null) {
                Gate::forUser($actor)->authorize('create', SchoolProfile::class);
            } else {
                Gate::forUser($actor)->authorize('update', $target);
            }

            return app(PublicContentManager::class)->saveSchoolProfile(
                $actor,
                $target,
                [...$data, ...$payload],
                $logo,
                $hero,
            );
        });
    }

    /**
     * Whether a stored field should be rendered as sanitized markup rather than
     * as escaped plain text.
     */
    public static function isRichText(?string $value): bool
    {
        return RichTextSanitizer::looksLikeRichText($value);
    }
}

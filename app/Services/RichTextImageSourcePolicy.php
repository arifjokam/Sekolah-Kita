<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Decides which <img> sources survive rich text sanitization.
 *
 * Image data is never accepted inline. The editor uploads through the backend,
 * MediaAssetService stores the sanitized source under a random UUID object key
 * on the public disk, and only the resulting public URL may appear in an
 * article body. Anything else - a data URL, a remote host, a private object
 * path, an asset that belongs to another article - resolves to null so the
 * element attribute is dropped.
 */
final class RichTextImageSourcePolicy
{
    /**
     * The only shape a stored object key can have: MediaAssetService mints
     * "media/<uuid>.<ext>" for every image it accepts.
     */
    private const OBJECT_KEY_PATTERN = '#^media/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(?:jpe?g|png|webp)$#i';

    /**
     * @param  list<string>|null  $allowedObjectKeys  null means "any public media object key"
     */
    private function __construct(private readonly ?array $allowedObjectKeys) {}

    public static function denyAll(): self
    {
        return new self([]);
    }

    /**
     * @param  list<string>  $allowedObjectKeys
     */
    public static function onlyObjectKeys(array $allowedObjectKeys): self
    {
        return new self(array_values(array_unique($allowedObjectKeys)));
    }

    /**
     * Accepts any object key this application could have written to the public
     * disk. Used when re-sanitizing content on read, where the reference table
     * is not loaded and the goal is only to refuse anything unrecognizable.
     */
    public static function publicMediaObjectKeys(): self
    {
        return new self(null);
    }

    /**
     * Resolve a submitted source to the canonical public URL, or null when the
     * source must be dropped.
     */
    public function canonicalUrl(string $value): ?string
    {
        $objectKey = self::objectKeyFrom($value);

        if ($objectKey === null) {
            return null;
        }

        if ($this->allowedObjectKeys !== null && ! in_array($objectKey, $this->allowedObjectKeys, true)) {
            return null;
        }

        return Storage::disk('public')->url($objectKey);
    }

    /**
     * Extract the stored object key a URL points at, or null when the URL is
     * not a public media URL.
     */
    public static function objectKeyFrom(string $value): ?string
    {
        $path = parse_url(trim($value), PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = ltrim(rawurldecode($path), '/');
        $storagePrefix = basename(rtrim((string) config('filesystems.disks.public.url'), '/')).'/';

        if (str_starts_with($path, $storagePrefix)) {
            $path = substr($path, strlen($storagePrefix));
        }

        return preg_match(self::OBJECT_KEY_PATTERN, $path) === 1 ? $path : null;
    }
}

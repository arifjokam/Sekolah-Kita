<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PublicContentCache
{
    private const TTL_SECONDS = 300;

    public function remember(string $group, string $key, Closure $callback): mixed
    {
        return Cache::remember(
            $this->entryKey($group, $key),
            self::TTL_SECONDS,
            $callback,
        );
    }

    public function invalidate(string ...$groups): void
    {
        foreach (array_unique($groups) as $group) {
            Cache::forget($this->versionKey($group));
        }
    }

    private function entryKey(string $group, string $key): string
    {
        return sprintf(
            'public-content:%s:%s:%s',
            $group,
            $this->version($group),
            hash('sha256', $key),
        );
    }

    private function version(string $group): string
    {
        return Cache::rememberForever(
            $this->versionKey($group),
            static fn (): string => (string) Str::uuid(),
        );
    }

    private function versionKey(string $group): string
    {
        return 'public-content:'.$group.':version';
    }
}

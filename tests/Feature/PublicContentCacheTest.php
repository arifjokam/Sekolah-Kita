<?php

namespace Tests\Feature;

use App\Models\AdmissionPeriod;
use App\Models\NewsArticle;
use App\Models\SchoolProfile;
use App\Models\User;
use App\Services\PublicContentReader;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicContentCacheTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_reads_are_cached_and_content_changes_invalidate_versioned_entries(): void
    {
        Cache::flush();
        SchoolProfile::factory()->create();
        $article = NewsArticle::factory()->published()->create(['title' => 'Cached title']);

        $this->get(route('news.index'))->assertSee('Cached title');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('news.index'))->assertSee('Cached title');
        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' ');
        DB::disableQueryLog();
        $this->assertStringNotContainsString('news_articles', $queries);

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->put(route('admin.news.update', $article), [
            'title' => 'Updated cached title',
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'privacy_review_confirmed' => '1',
        ])->assertRedirect(route('admin.news.index'));

        $this->get(route('news.index'))->assertSee('Updated cached title')->assertDontSee('Cached title');
    }

    public function test_public_admission_period_projection_does_not_expose_internal_quota(): void
    {
        $period = AdmissionPeriod::factory()->create(['quota' => 99]);

        $projection = app(PublicContentReader::class)->admissionPeriods();

        $this->assertIsArray($projection);
        $this->assertArrayNotHasKey('quota', $projection[0]);
        $this->assertSame($period->id, $projection[0]['id']);
    }

    public function test_public_reader_returns_only_safe_array_fields(): void
    {
        SchoolProfile::factory()->create();
        $article = NewsArticle::factory()->published()->create([
            'title' => 'Safe projection',
            'body' => 'Safe body',
            'author_user_id' => User::factory()->administrator()->create()->id,
        ]);

        $projection = app(PublicContentReader::class)->newsArticle($article->slug);

        $this->assertIsArray($projection);
        $this->assertArrayHasKey('title', $projection);
        $this->assertArrayNotHasKey('author_user_id', $projection);
        $this->assertArrayNotHasKey('status', $projection);
        $this->assertArrayNotHasKey('cover_media_id', $projection);
    }
}

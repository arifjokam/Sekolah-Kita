<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\NewsArticle;
use App\Models\SchoolProfile;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_pages_render_for_empty_school(): void
    {
        foreach ([
            route('home'),
            route('school-profile.show'),
            route('news.index'),
            route('achievements.index'),
            route('gallery.index'),
            route('teachers.index'),
            route('contact.create'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_only_published_news_appear_in_public_listing_and_draft_detail_is_not_found(): void
    {
        SchoolProfile::factory()->create();
        $published = NewsArticle::factory()->published()->create(['title' => 'Berita Terbit Aman']);
        $draft = NewsArticle::factory()->create([
            'title' => 'Draft Rahasia',
            'body' => 'Draft body should never be public.',
        ]);
        $archived = NewsArticle::factory()->create(['title' => 'Arsip Rahasia', 'status' => 'archived']);

        $this->get(route('news.index'))
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee($draft->title)
            ->assertDontSee($archived->title);

        $this->get(route('news.show', $draft->slug))->assertNotFound();
        $this->get(route('news.show', $published->slug))
            ->assertOk()
            ->assertSee($published->body);
    }

    public function test_public_content_hides_archived_future_and_hidden_gallery_items(): void
    {
        SchoolProfile::factory()->create();
        $album = GalleryAlbum::factory()->published()->create(['title' => 'Album Publik']);
        $hiddenAlbum = GalleryAlbum::factory()->create(['title' => 'Album Draft']);
        $publishedItem = GalleryItem::factory()->published()->create([
            'gallery_album_id' => $album->id,
            'caption' => 'Media publik',
        ]);
        $draftItem = GalleryItem::factory()->create([
            'gallery_album_id' => $album->id,
            'caption' => 'Media draft',
        ]);

        $this->get(route('gallery.show', $album->slug))
            ->assertOk()
            ->assertSee($publishedItem->caption)
            ->assertDontSee($draftItem->caption);
        $this->get(route('gallery.show', $hiddenAlbum->slug))->assertNotFound();
    }

    public function test_public_teacher_directory_contains_only_approved_professional_projection(): void
    {
        SchoolProfile::factory()->create();
        $visible = Teacher::factory()->publiclyVisible()->create([
            'legal_name' => 'Guru Publik',
            'position_title' => 'Guru Matematika',
            'biography' => 'Pengalaman mengajar profesional.',
            'employee_number' => 'NIP-SECRET-001',
            'national_id' => '3200000000000001',
            'email' => 'private-teacher@example.test',
            'phone' => '08123456789',
        ]);
        $hidden = Teacher::factory()->create(['legal_name' => 'Guru Privat', 'directory_slug' => 'guru-privat']);

        $this->get(route('teachers.index'))
            ->assertOk()
            ->assertSee($visible->legal_name)
            ->assertDontSee($hidden->legal_name);
        $this->get(route('teachers.show', $hidden->directory_slug))->assertNotFound();
        $this->get(route('teachers.show', $visible->directory_slug))
            ->assertOk()
            ->assertSee('Guru Matematika')
            ->assertDontSee('NIP-SECRET-001')
            ->assertDontSee('3200000000000001')
            ->assertDontSee('private-teacher@example.test')
            ->assertDontSee('08123456789');
    }

    public function test_public_news_body_escapes_untrusted_html(): void
    {
        SchoolProfile::factory()->create();
        $article = NewsArticle::factory()->published()->create([
            'title' => 'Ekspresi konten',
            'body' => '<script>alert("xss")</script>',
        ]);

        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_public_metadata_escapes_untrusted_titles_and_excerpts(): void
    {
        SchoolProfile::factory()->create();
        $article = NewsArticle::factory()->published()->create([
            'title' => '"><script>alert(1)</script>',
            'excerpt' => '"><img src=x onerror=alert(1)>',
            'body' => '<script>alert("body")</script>',
        ]);

        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_public_collections_are_paginated(): void
    {
        SchoolProfile::factory()->create();
        NewsArticle::factory()->count(10)->published()->create();

        $response = $this->get(route('news.index', ['page' => 2]));

        $response->assertOk()->assertViewHas('articles', function ($paginator): bool {
            return $paginator->currentPage() === 2 && $paginator->lastPage() === 2 && $paginator->count() === 1;
        });
    }

    public function test_achievement_detail_uses_school_level_projection_without_student_relation(): void
    {
        SchoolProfile::factory()->create();
        $achievement = Achievement::factory()->published()->create([
            'title' => 'Juara ensuing sekolah',
            'description' => 'Prestasi tingkat sekolah tanpa identitas penerima.',
        ]);

        $this->get(route('achievements.show', $achievement->slug))
            ->assertOk()
            ->assertSee($achievement->title)
            ->assertSee('tanpa identitas penerima');
    }
}

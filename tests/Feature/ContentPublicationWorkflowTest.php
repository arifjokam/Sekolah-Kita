<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Models\Achievement;
use App\Models\ContactMessage;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\NewsArticle;
use App\Models\SchoolProfile;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ContentPublicationWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_creates_news_as_draft_and_publishes_it(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title' => 'Kabar sekolah',
            'slug' => 'kabar-sekolah',
            'excerpt' => 'Ringkasan kabar.',
            'body' => 'Isi kabar sekolah.',
            'privacy_review_confirmed' => '1',
            'status' => 'published',
        ])->assertRedirect(route('admin.news.index'));

        $article = NewsArticle::query()->firstOrFail();
        $this->assertSame(PublicationStatus::Draft, $article->status);
        $this->assertNull($article->published_at);
        $this->assertSame($admin->id, $article->author_user_id);

        $this->actingAs($admin)->post(route('admin.news.publish', $article))->assertRedirect();
        $this->assertSame(PublicationStatus::Published, $article->refresh()->status);
        $this->assertNotNull($article->published_at);
    }

    public function test_unpublishing_and_archiving_remove_news_from_public_reads(): void
    {
        $admin = User::factory()->administrator()->create();
        $article = NewsArticle::factory()->published()->create(['title' => 'Berita Publik']);
        $this->get(route('news.index'))->assertSee('Berita Publik');

        $this->actingAs($admin)->post(route('admin.news.unpublish', $article))->assertRedirect();
        $this->get(route('news.index'))->assertDontSee('Berita Publik');
        $this->assertSame(PublicationStatus::Draft, $article->refresh()->status);

        $this->actingAs($admin)->post(route('admin.news.publish', $article));
        $this->actingAs($admin)->post(route('admin.news.archive', $article))->assertRedirect();
        $this->get(route('news.index'))->assertDontSee('Berita Publik');
        $this->assertSame(PublicationStatus::Archived, $article->refresh()->status);
    }

    public function test_non_administrators_cannot_access_publication_actions(): void
    {
        $article = NewsArticle::factory()->create();

        foreach ([User::factory()->guru()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.news.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.news.publish', $article))->assertForbidden();
        }

        $this->actingAsGuest()->get(route('admin.news.index'))->assertRedirect(route('login'));
    }

    public function test_news_validation_rejects_duplicate_slug_and_ignores_client_status(): void
    {
        $admin = User::factory()->administrator()->create();
        NewsArticle::factory()->create(['slug' => 'satu']);

        $this->actingAs($admin)->from(route('admin.news.create'))->post(route('admin.news.store'), [
            'title' => 'Judul',
            'slug' => 'satu',
            'excerpt' => 'Ringkasan',
            'body' => 'Isi',
            'privacy_review_confirmed' => '1',
        ])->assertSessionHasErrors('slug');

        $this->assertDatabaseMissing('news_articles', ['title' => 'Judul']);
    }

    public function test_gallery_album_and_item_publication_workflow_is_scoped(): void
    {
        $admin = User::factory()->administrator()->create();
        $album = GalleryAlbum::factory()->create();
        $otherAlbum = GalleryAlbum::factory()->create();
        $item = GalleryItem::factory()->create(['gallery_album_id' => $album->id]);

        $this->actingAs($admin)->post(route('admin.gallery-albums.items.publish', [$otherAlbum, $item]))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.gallery-albums.items.publish', [$album, $item]))->assertRedirect();
        $this->assertSame('published', $item->refresh()->status->value);
    }

    public function test_teacher_publication_requires_an_active_profile_and_slug(): void
    {
        $admin = User::factory()->administrator()->create();
        $teacher = Teacher::factory()->create(['directory_slug' => 'guru-aktif']);

        $this->actingAs($admin)->post(route('admin.teachers.publish', $teacher))->assertRedirect();
        $this->assertTrue($teacher->refresh()->is_publicly_visible);

        $teacher->update(['employment_status' => TeacherEmploymentStatus::Retired]);
        $this->actingAs($admin)->post(route('admin.teachers.publish', $teacher))->assertSessionHasErrors('teacher');
        $this->assertFalse($teacher->refresh()->is_publicly_visible);
    }

    public function test_administrator_updates_school_profile_and_public_page_reflects_it(): void
    {
        $admin = User::factory()->administrator()->create();
        SchoolProfile::factory()->create(['public_name' => 'Nama Lama']);

        $this->actingAs($admin)->put(route('admin.school-profile.update'), [
            'legal_name' => 'Sekolah Baru',
            'public_name' => 'Nama Publik Baru',
            'short_name' => 'SNB',
            'address_line' => 'Jl. sekolah',
            'city' => 'Bandung',
            'postal_code' => '40111',
            'public_phone' => '022-123456',
            'public_email' => 'public@example.test',
            'vision' => 'Visi baru',
            'mission' => 'Misi baru',
            'about' => 'Tentang sekolah baru',
        ])->assertRedirect(route('admin.school-profile.edit'));

        $this->get(route('school-profile.show'))
            ->assertOk()
            ->assertSee('Nama Publik Baru')
            ->assertSee('public@example.test')
            ->assertDontSee('Nama Lama');
    }

    public function test_achievement_and_album_publication_transitions_are_available(): void
    {
        $admin = User::factory()->administrator()->create();
        $achievement = Achievement::factory()->create();
        $album = GalleryAlbum::factory()->create();

        $this->actingAs($admin)->post(route('admin.achievements.publish', $achievement))->assertRedirect();
        $this->assertSame('published', $achievement->refresh()->status->value);

        $this->actingAs($admin)->post(route('admin.gallery-albums.publish', $album))->assertRedirect();
        $this->assertSame('published', $album->refresh()->status->value);
    }

    public function test_published_achievement_cannot_be_moved_to_a_future_date(): void
    {
        $admin = User::factory()->administrator()->create();
        $achievement = Achievement::factory()->published()->create([
            'achievement_date' => now()->subWeek()->format('Y-m-d'),
        ]);
        $originalDate = $achievement->achievement_date->format('Y-m-d');

        $this->actingAs($admin)->from(route('admin.achievements.edit', $achievement))
            ->put(route('admin.achievements.update', $achievement), [
                'title' => $achievement->title,
                'slug' => $achievement->slug,
                'description' => $achievement->description,
                'category' => $achievement->category,
                'level' => $achievement->level,
                'achievement_date' => now()->addMonth()->format('Y-m-d'),
                'privacy_review_confirmed' => '1',
            ])
            ->assertSessionHasErrors('achievement_date');

        $this->assertSame($originalDate, $achievement->refresh()->achievement_date->format('Y-m-d'));
        $this->assertSame('published', $achievement->status->value);

        $this->actingAs($admin)->from(route('admin.achievements.edit', $achievement))
            ->put(route('admin.achievements.update', $achievement), [
                'title' => $achievement->title,
                'slug' => $achievement->slug,
                'description' => 'Deskripsi diperbarui.',
                'category' => $achievement->category,
                'level' => $achievement->level,
                'achievement_date' => now()->subDay()->format('Y-m-d'),
                'privacy_review_confirmed' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.achievements.index'));
    }

    public function test_draft_achievement_may_keep_a_future_date_until_publication(): void
    {
        $admin = User::factory()->administrator()->create();
        $achievement = Achievement::factory()->create([
            'achievement_date' => now()->addMonth()->format('Y-m-d'),
        ]);

        $this->actingAs($admin)->from(route('admin.achievements.edit', $achievement))
            ->put(route('admin.achievements.update', $achievement), [
                'title' => $achievement->title,
                'slug' => $achievement->slug,
                'description' => $achievement->description,
                'category' => $achievement->category,
                'level' => $achievement->level,
                'achievement_date' => now()->addMonths(2)->format('Y-m-d'),
                'privacy_review_confirmed' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->from(route('admin.achievements.index'))
            ->post(route('admin.achievements.publish', $achievement))
            ->assertSessionHasErrors('achievement_date');
        $this->assertSame('draft', $achievement->refresh()->status->value);
    }

    public function test_administrator_publication_screens_render_for_each_content_type(): void
    {
        $admin = User::factory()->administrator()->create();
        $news = NewsArticle::factory()->create();
        $achievement = Achievement::factory()->create();
        $album = GalleryAlbum::factory()->create();
        $item = GalleryItem::factory()->create(['gallery_album_id' => $album->id]);
        $teacher = Teacher::factory()->create();
        $message = ContactMessage::factory()->create();
        SchoolProfile::factory()->create();

        foreach ([
            route('admin.foundation'),
            route('admin.school-profile.edit'),
            route('admin.news.index'),
            route('admin.news.create'),
            route('admin.news.edit', $news),
            route('admin.achievements.index'),
            route('admin.achievements.create'),
            route('admin.achievements.edit', $achievement),
            route('admin.gallery-albums.index'),
            route('admin.gallery-albums.create'),
            route('admin.gallery-albums.edit', $album),
            route('admin.gallery-albums.items.index', $album),
            route('admin.gallery-albums.items.create', $album),
            route('admin.gallery-albums.items.edit', [$album, $item]),
            route('admin.teachers.index'),
            route('admin.teachers.edit', $teacher),
            route('admin.contact-messages.index'),
            route('admin.contact-messages.show', $message),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}

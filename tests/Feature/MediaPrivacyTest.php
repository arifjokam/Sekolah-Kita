<?php

namespace Tests\Feature;

use App\Enums\MediaScanStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\SchoolProfile;
use App\Models\Teacher;
use App\Models\User;
use App\Services\MediaAssetService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaPrivacyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_upload_is_sanitized_private_and_randomized_before_publication(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();

        $media = app(MediaAssetService::class)->store(
            UploadedFile::fake()->image('nama-pribadi.jpg', 40, 30),
            $admin,
        );

        $this->assertSame('private', $media->visibility->value);
        $this->assertStringStartsWith('media/', $media->private_object_key);
        $this->assertMatchesRegularExpression('/media\/[0-9a-f-]+\.jpg$/', $media->private_object_key);
        $this->assertNotSame('nama-pribadi.jpg', $media->private_object_key);
        Storage::disk('local')->assertExists($media->private_object_key);
        Storage::disk('public')->assertEmpty();
        $this->assertNotNull($media->metadata_sanitized_at);
    }

    public function test_publication_promotes_media_and_unpublication_removes_public_derivative(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $media = app(MediaAssetService::class)->store(UploadedFile::fake()->image('cover.jpg'), $admin);
        $article = NewsArticle::factory()->create(['cover_media_id' => $media->id]);

        $this->actingAs($admin)->post(route('admin.news.publish', $article))->assertRedirect();
        $media->refresh();
        $this->assertSame('public', $media->visibility->value);
        $this->assertNotNull($media->public_object_key);
        Storage::disk('public')->assertExists($media->public_object_key);
        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertSee($media->public_object_key, false)
            ->assertDontSee($media->private_object_key, false);

        $this->actingAs($admin)->post(route('admin.news.unpublish', $article))->assertRedirect();
        $media->refresh();
        $this->assertSame('private', $media->visibility->value);
        $this->assertNull($media->public_object_key);
        Storage::disk('public')->assertEmpty();
    }

    public function test_valid_gallery_upload_is_stored_privately_as_a_draft(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $album = GalleryAlbum::factory()->create();

        $this->actingAs($admin)->post(route('admin.gallery-albums.items.store', $album), [
            'caption' => 'Kegiatan sekolah',
            'alt_text' => 'Suasana kegiatan sekolah',
            'event_date' => now()->subDay()->format('Y-m-d'),
            'sort_order' => 0,
            'privacy_review_confirmed' => '1',
            'image' => UploadedFile::fake()->image('kegiatan.jpg'),
        ])->assertRedirect();

        $item = GalleryItem::query()->firstOrFail();
        $this->assertSame('draft', $item->status->value);
        $this->assertSame('private', $item->mediaAsset->visibility->value);
        Storage::disk('local')->assertExists($item->mediaAsset->private_object_key);
    }

    public function test_media_upload_rejects_svg_and_disguised_non_image_content(): void
    {
        Storage::fake('local');
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->from(route('admin.gallery-albums.create'))->post(route('admin.gallery-albums.items.store', GalleryAlbum::factory()->create()), [
            'caption' => 'Tidak valid',
            'alt_text' => 'Deskripsi',
            'sort_order' => 0,
            'privacy_review_confirmed' => '1',
            'image' => UploadedFile::fake()->create('unsafe.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('image');

        $this->assertSame(0, MediaAsset::query()->count());
        $this->assertNotNull($admin);
    }

    public function test_public_media_is_hidden_when_scan_is_not_clean(): void
    {
        $media = MediaAsset::factory()->public()->create([
            'scan_status' => MediaScanStatus::Pending,
        ]);
        $article = NewsArticle::factory()->published()->create(['cover_media_id' => $media->id]);

        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertDontSee($media->public_object_key, false);
    }

    public function test_private_paths_and_hidden_media_metadata_are_not_serialized(): void
    {
        $media = MediaAsset::factory()->create(['private_object_key' => 'media/private-secret.jpg']);

        $this->assertArrayNotHasKey('private_object_key', $media->toArray());
        $this->assertArrayNotHasKey('public_object_key', $media->toArray());
        $this->assertArrayNotHasKey('checksum_sha256', $media->toArray());
    }

    public function test_replacing_a_published_news_cover_withdraws_the_previous_public_derivative(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $original = app(MediaAssetService::class)->store(UploadedFile::fake()->image('lama.jpg'), $admin);
        $article = NewsArticle::factory()->published()->create(['cover_media_id' => $original->id]);
        app(MediaAssetService::class)->synchronize($original);
        $original->refresh();
        $this->assertSame('public', $original->visibility->value);

        $this->actingAs($admin)->put(route('admin.news.update', $article), [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'privacy_review_confirmed' => '1',
            'cover_image' => UploadedFile::fake()->image('baru.jpg'),
        ])->assertRedirect(route('admin.news.index'));

        $original->refresh();
        $this->assertSame('private', $original->visibility->value);
        $this->assertNull($original->public_object_key);
        Storage::disk('public')->assertMissing($original->public_object_key ?? 'media/tidak-ada.jpg');
        Storage::disk('local')->assertExists($original->private_object_key);

        $replacement = $article->refresh()->coverMedia;
        $this->assertNotSame($original->id, $replacement->id);
        $this->assertSame('public', $replacement->visibility->value);
        Storage::disk('public')->assertExists($replacement->public_object_key);

        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertSee($replacement->public_object_key, false)
            ->assertDontSee($original->private_object_key, false);
    }

    public function test_removing_the_school_profile_logo_withdraws_its_public_derivative(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $logo = app(MediaAssetService::class)->store(UploadedFile::fake()->image('logo.jpg'), $admin);
        $profile = SchoolProfile::factory()->create(['logo_media_id' => $logo->id]);
        app(MediaAssetService::class)->synchronize($logo);
        $this->assertSame('public', $logo->refresh()->visibility->value);

        $this->actingAs($admin)->put(route('admin.school-profile.update'), [
            'legal_name' => $profile->legal_name,
            'remove_logo' => '1',
        ])->assertRedirect(route('admin.school-profile.edit'));

        $this->assertNull($profile->refresh()->logo_media_id);
        $logo->refresh();
        $this->assertSame('private', $logo->visibility->value);
        $this->assertNull($logo->public_object_key);
        $this->assertNull($logo->published_at);
        $this->get(route('school-profile.show'))->assertOk()->assertDontSee($logo->public_object_key ?? 'media/tidak-ada.jpg', false);
    }

    public function test_removing_a_teacher_photo_withdraws_its_public_derivative(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $photo = app(MediaAssetService::class)->store(UploadedFile::fake()->image('guru.jpg'), $admin);
        $teacher = Teacher::factory()->publiclyVisible()->create(['photo_media_id' => $photo->id]);
        app(MediaAssetService::class)->synchronize($photo);
        $this->assertSame('public', $photo->refresh()->visibility->value);
        $this->get(route('teachers.show', $teacher->directory_slug))
            ->assertOk()
            ->assertSee($photo->public_object_key, false);

        $this->actingAs($admin)->put(route('admin.teacher-directory.update', $teacher), [
            'directory_slug' => $teacher->directory_slug,
            'position_title' => $teacher->position_title,
            'remove_photo' => '1',
        ])->assertRedirect(route('admin.teacher-directory.index'));

        $this->assertNull($teacher->refresh()->photo_media_id);
        $photo->refresh();
        $this->assertSame('private', $photo->visibility->value);
        $this->assertNull($photo->public_object_key);
        $this->get(route('teachers.show', $teacher->directory_slug))
            ->assertOk()
            ->assertDontSee($photo->private_object_key, false);
    }

    public function test_direct_teacher_employment_change_withdraws_the_public_photo_derivative(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $photo = MediaAsset::factory()->public()->create();
        $teacher = Teacher::factory()->publiclyVisible()->create(['photo_media_id' => $photo->id]);

        $teacher->update(['employment_status' => TeacherEmploymentStatus::Retired]);

        $photo->refresh();
        $this->assertSame('private', $photo->visibility->value);
        $this->assertNull($photo->public_object_key);
        Storage::disk('public')->assertEmpty();
    }

    public function test_direct_teacher_photo_swap_withdraws_the_previous_public_derivative(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $original = MediaAsset::factory()->public()->create();
        $replacement = MediaAsset::factory()->create();
        Storage::disk('local')->put($replacement->private_object_key, 'binary');
        Storage::disk('public')->put($original->public_object_key, 'binary');
        $teacher = Teacher::factory()->publiclyVisible()->create(['photo_media_id' => $original->id]);

        $teacher->update(['photo_media_id' => $replacement->id]);

        $this->assertSame('private', $original->refresh()->visibility->value);
        $this->assertNull($original->public_object_key);
        $replacement->refresh();
        $this->assertSame('public', $replacement->visibility->value);
        Storage::disk('public')->assertExists($replacement->public_object_key);
    }
}

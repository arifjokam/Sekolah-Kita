<?php

namespace Tests\Feature;

use App\Enums\MediaVisibility;
use App\Enums\PublicationStatus;
use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\NewsArticleMedia;
use App\Models\User;
use App\Services\MediaAssetService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsRichTextEditorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const RICH_BODY = '<h2>Judul bagian</h2>'
        .'<p>Paragraf dengan <strong>tebal</strong> dan <em>miring</em>.</p>'
        .'<ul><li>Satu</li><li>Dua</li></ul>';

    public function test_administrator_creates_an_article_with_rich_html(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title' => 'Berita kaya teks',
            'slug' => 'berita-kaya-teks',
            'excerpt' => 'Ringkasan berita.',
            'body' => self::RICH_BODY,
            'privacy_review_confirmed' => '1',
        ])->assertRedirect(route('admin.news.index'))->assertSessionHasNoErrors();

        $article = NewsArticle::query()->firstOrFail();

        $this->assertSame(PublicationStatus::Draft, $article->status);
        $this->assertStringContainsString('<h2>Judul bagian</h2>', $article->body);
        $this->assertStringContainsString('<strong>tebal</strong>', $article->body);
        $this->assertStringContainsString('<ul><li>Satu</li><li>Dua</li></ul>', $article->body);
    }

    public function test_administrator_edits_an_article_with_rich_html(): void
    {
        $admin = User::factory()->administrator()->create();
        $article = NewsArticle::factory()->create(['body' => 'Teks lama.']);

        $this->actingAs($admin)->put(route('admin.news.update', $article), [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => self::RICH_BODY,
        ])->assertRedirect(route('admin.news.index'))->assertSessionHasNoErrors();

        $this->assertStringContainsString('<h2>Judul bagian</h2>', $article->refresh()->body);
    }

    public function test_stored_body_never_keeps_scripts_handlers_or_dangerous_links(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title' => 'Berita Berbahaya',
            'slug' => 'berita-berbahaya',
            'excerpt' => 'Ringkasan.',
            'body' => '<p onclick="steal()">Teks</p><script>alert("xss")</script>'
                .'<a href="javascript:alert(1)">jahat</a><a href="https://sekolah.sch.id">aman</a>',
        ])->assertRedirect(route('admin.news.index'));

        $body = NewsArticle::query()->firstOrFail()->body;

        $this->assertStringNotContainsString('script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('<p>Teks</p>', $body);
        $this->assertStringContainsString('href="https://sekolah.sch.id"', $body);
    }

    public function test_image_upload_returns_a_public_url_and_no_internal_paths(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.news.images.store'), [
            'image' => UploadedFile::fake()->image('kegiatan sekolah.jpg', 120, 90),
        ])->assertCreated();

        $payload = $response->json();

        $this->assertIsInt($payload['id']);
        $this->assertSame(MediaAssetService::MAX_IMAGE_BYTES, $payload['max_bytes']);
        $this->assertSame('image/jpeg', $payload['mime_type']);
        $this->assertStringNotContainsString('kegiatan sekolah', json_encode($payload));

        $media = MediaAsset::query()->findOrFail($payload['id']);
        $this->assertSame(MediaVisibility::Public, $media->visibility);
        $this->assertSame(
            Storage::disk('public')->url($media->public_object_key),
            $payload['url'],
        );
        $this->assertStringNotContainsString($media->private_object_key, json_encode($payload));
        $this->assertStringNotContainsString($media->private_object_key, $payload['url']);

        // The create screen has no article yet, so the image is staged until the
        // article that uses it is saved.
        $this->assertDatabaseHas('news_article_media', [
            'news_article_id' => null,
            'media_asset_id' => $media->id,
        ]);
    }

    public function test_saved_body_keeps_an_uploaded_image_and_records_the_reference(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();

        $upload = $this->actingAs($admin)
            ->postJson(route('admin.news.images.store'), [
                'image' => UploadedFile::fake()->image('kegiatan.jpg', 200, 150),
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title' => 'Berita bergambar',
            'slug' => 'berita-bergambar',
            'excerpt' => 'Ringkasan.',
            'body' => '<p>Foto kegiatan.</p><img src="'.$upload['url'].'" alt="Suasana kegiatan" data-width="75">',
        ])->assertRedirect(route('admin.news.index'));

        $article = NewsArticle::query()->firstOrFail();
        $media = MediaAsset::query()->findOrFail($upload['id']);

        $this->assertStringContainsString('<img', $article->body);
        $this->assertStringContainsString('alt="Suasana kegiatan"', $article->body);
        $this->assertStringContainsString('data-width="75"', $article->body);
        $this->assertDatabaseHas('news_article_media', [
            'news_article_id' => $article->id,
            'media_asset_id' => $media->id,
        ]);
        $this->assertDatabaseMissing('news_article_media', [
            'news_article_id' => null,
            'media_asset_id' => $media->id,
        ]);
    }

    public function test_body_images_uploaded_for_an_article_are_attached_to_it(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $article = NewsArticle::factory()->create();

        $upload = $this->actingAs($admin)
            ->postJson(route('admin.news.images.store-for-article', $article), [
                'image' => UploadedFile::fake()->image('sekolah.jpg'),
            ])
            ->assertCreated()
            ->json();

        $this->assertDatabaseHas('news_article_media', [
            'news_article_id' => $article->id,
            'media_asset_id' => $upload['id'],
        ]);
    }

    public function test_body_cannot_reference_an_image_uploaded_for_another_article(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $other = NewsArticle::factory()->create();

        $foreign = $this->actingAs($admin)
            ->postJson(route('admin.news.images.store-for-article', $other), [
                'image' => UploadedFile::fake()->image('lain.jpg'),
            ])
            ->assertCreated()
            ->json();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title' => 'Berita pencuri gambar',
            'slug' => 'berita-pencuri-gambar',
            'excerpt' => 'Ringkasan.',
            'body' => '<p>Isi.</p><img src="'.$foreign['url'].'">',
        ])->assertRedirect(route('admin.news.index'));

        $article = NewsArticle::query()->where('slug', 'berita-pencuri-gambar')->firstOrFail();

        $this->assertStringNotContainsString('<img', $article->body);
        $this->assertDatabaseMissing('news_article_media', [
            'news_article_id' => $article->id,
            'media_asset_id' => $foreign['id'],
        ]);
        $this->assertDatabaseHas('news_article_media', [
            'news_article_id' => $other->id,
            'media_asset_id' => $foreign['id'],
        ]);
    }

    public function test_body_cannot_reference_another_administrators_staged_image(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $first = User::factory()->administrator()->create();
        $second = User::factory()->administrator()->create();

        $staged = $this->actingAs($first)
            ->postJson(route('admin.news.images.store'), ['image' => UploadedFile::fake()->image('milik-lain.jpg')])
            ->assertCreated()
            ->json();

        $this->actingAs($second)->post(route('admin.news.store'), [
            'title' => 'Berita/admin lain',
            'slug' => 'berita-admin-lain',
            'excerpt' => 'Ringkasan.',
            'body' => '<p>Isi.</p><img src="'.$staged['url'].'">',
        ])->assertRedirect(route('admin.news.index'));

        $this->assertStringNotContainsString(
            '<img',
            NewsArticle::query()->where('slug', 'berita-admin-lain')->firstOrFail()->body,
        );
    }

    public function test_removing_an_image_from_the_body_withdraws_its_public_derivative(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $article = NewsArticle::factory()->published()->create();

        $upload = $this->actingAs($admin)
            ->postJson(route('admin.news.images.store-for-article', $article), [
                'image' => UploadedFile::fake()->image('dihapus.jpg'),
            ])
            ->assertCreated()
            ->json();

        $media = MediaAsset::query()->findOrFail($upload['id']);
        $this->assertSame(MediaVisibility::Public, $media->visibility);

        $this->actingAs($admin)->put(route('admin.news.update', $article), [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => '<p>Gambar sudah dihapus.</p>',
        ])->assertRedirect(route('admin.news.index'));

        $this->assertStringNotContainsString('<img', $article->refresh()->body);
        $this->assertDatabaseMissing('news_article_media', [
            'news_article_id' => $article->id,
            'media_asset_id' => $media->id,
        ]);
        $this->assertSame(MediaVisibility::Private, $media->refresh()->visibility);
        Storage::disk('public')->assertMissing($media->public_object_key);
    }

    public function test_unauthorized_users_cannot_upload_article_images(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $article = NewsArticle::factory()->create();

        foreach ([User::factory()->guru()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)
                ->postJson(route('admin.news.images.store'), ['image' => UploadedFile::fake()->image('x.jpg')])
                ->assertForbidden();

            $this->actingAs($user)
                ->postJson(route('admin.news.images.store-for-article', $article), ['image' => UploadedFile::fake()->image('x.jpg')])
                ->assertForbidden();
        }

        $this->assertSame(0, MediaAsset::query()->count());
    }

    public function test_guests_cannot_reach_the_article_image_upload_endpoint(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->postJson(route('admin.news.images.store'), ['image' => UploadedFile::fake()->image('x.jpg')])
            ->assertUnauthorized();

        $this->assertSame(0, MediaAsset::query()->count());
    }

    public function test_image_upload_rejects_unapproved_types_and_oversized_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->postJson(route('admin.news.images.store'), [
            'image' => UploadedFile::fake()->create('gambar.svg', 10, 'image/svg+xml'),
        ])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->actingAs($admin)->postJson(route('admin.news.images.store'), [
            'image' => UploadedFile::fake()->create('bukan-gambar.jpg', 10, 'text/plain'),
        ])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->actingAs($admin)->postJson(route('admin.news.images.store'), [
            'image' => UploadedFile::fake()->image('besar.jpg')->size(6 * 1024),
        ])->assertStatus(422)->assertJsonValidationErrors('image');

        $this->postJson(route('admin.news.images.store'), [])->assertStatus(422);

        $this->assertSame(0, MediaAsset::query()->count());
        Storage::disk('local')->assertEmpty();
    }

    public function test_existing_plain_text_article_still_renders_as_plain_text(): void
    {
        $body = "Baris pertama.\nBaris kedua dengan <b>penanda</b>.";
        $article = NewsArticle::factory()->published()->create(['body' => $body]);

        $this->actingAs(User::factory()->administrator()->create())
            ->put(route('admin.news.update', $article), [
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'body' => $body,
            ])
            ->assertRedirect(route('admin.news.index'));

        $this->assertSame($body, $article->refresh()->body);

        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertSee('Baris pertama.', false)
            ->assertSee('Baris kedua dengan &lt;b&gt;penanda&lt;/b&gt;.', false);
    }

    public function test_public_article_renders_sanitized_rich_content(): void
    {
        $article = NewsArticle::factory()->published()->create([
            'body' => '<h2>Agenda</h2><ul><li>Satu</li></ul><p onclick="steal()">Rincian</p><script>alert(1)</script>',
        ]);

        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertSee('<h2>Agenda</h2>', false)
            ->assertSee('<ul><li>Satu</li></ul>', false)
            ->assertSee('<p>Rincian</p>', false)
            ->assertDontSee('onclick', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_public_article_never_renders_an_unapproved_image_source(): void
    {
        $article = NewsArticle::factory()->published()->create([
            'body' => '<p>Isi.</p><img src="https://penacak.example.test/pixel.gif" alt="pelacak">'
                .'<img src="data:image/png;base64,iVBORw0KGgo=">',
        ]);

        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertDontSee('penacak.example.test', false)
            ->assertDontSee('base64', false);
    }

    public function test_publication_workflow_is_unchanged_for_rich_articles(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.news.store'), [
            'title' => 'Alur terbitan',
            'slug' => 'alur-terbitan',
            'excerpt' => 'Ringkasan.',
            'body' => self::RICH_BODY,
            'status' => 'published',
        ])->assertRedirect(route('admin.news.index'));

        $article = NewsArticle::query()->firstOrFail();
        $this->assertSame(PublicationStatus::Draft, $article->status);
        $this->get(route('news.index'))->assertDontSee('Alur terbitan');

        $this->actingAs($admin)->post(route('admin.news.publish', $article))->assertRedirect();
        $this->assertSame(PublicationStatus::Published, $article->refresh()->status);
        $this->get(route('news.index'))->assertSee('Alur terbitan');
        $this->get(route('news.show', $article->slug))->assertSee('Judul bagian', false);

        $this->actingAs($admin)->post(route('admin.news.unpublish', $article))->assertRedirect();
        $this->get(route('news.index'))->assertDontSee('Alur terbitan');

        $this->actingAs($admin)->post(route('admin.news.publish', $article));
        $this->actingAs($admin)->post(route('admin.news.archive', $article))->assertRedirect();
        $this->assertSame(PublicationStatus::Archived, $article->refresh()->status);
        $this->get(route('news.show', $article->slug))->assertNotFound();
    }

    public function test_body_image_keeps_working_across_publication_state_changes(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $article = NewsArticle::factory()->create();

        $upload = $this->actingAs($admin)
            ->postJson(route('admin.news.images.store-for-article', $article), [
                'image' => UploadedFile::fake()->image('tetap.jpg'),
            ])
            ->assertCreated()
            ->json();

        $media = MediaAsset::query()->findOrFail($upload['id']);
        $publicObjectKey = $media->public_object_key;

        $this->actingAs($admin)->put(route('admin.news.update', $article), [
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'body' => '<p>Dengan gambar.</p><img src="'.$upload['url'].'" alt="Tetap tampil">',
        ])->assertRedirect(route('admin.news.index'));

        $this->actingAs($admin)->post(route('admin.news.publish', $article));
        $this->assertStringContainsString($publicObjectKey, $article->refresh()->body, false);
        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertSee($publicObjectKey, false);

        $this->actingAs($admin)->post(route('admin.news.unpublish', $article));

        // The stored URL has to keep resolving after a publication round trip,
        // otherwise the article would come back with a broken image.
        $media->refresh();
        $this->assertSame(MediaVisibility::Public, $media->visibility);
        $this->assertSame($publicObjectKey, $media->public_object_key);
        $this->assertStringContainsString($publicObjectKey, $article->refresh()->body, false);
    }

    public function test_abandoned_staged_uploads_are_pruned_without_touching_saved_images(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $article = NewsArticle::factory()->create();

        $stale = $this->actingAs($admin)
            ->postJson(route('admin.news.images.store'), ['image' => UploadedFile::fake()->image('tertinggal.jpg')])
            ->assertCreated()
            ->json();

        $fresh = $this->actingAs($admin)
            ->postJson(route('admin.news.images.store-for-article', $article), ['image' => UploadedFile::fake()->image('dipakai.jpg')])
            ->assertCreated()
            ->json();

        NewsArticleMedia::query()->where('media_asset_id', $stale['id'])->update([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->artisan('news:prune-staged-article-images')->assertSuccessful();

        $this->assertDatabaseMissing('news_article_media', ['media_asset_id' => $stale['id']]);
        $this->assertSame('deleted', MediaAsset::query()->findOrFail($stale['id'])->processing_status->value);
        $this->assertDatabaseHas('news_article_media', [
            'news_article_id' => $article->id,
            'media_asset_id' => $fresh['id'],
        ]);
        $this->assertSame(
            MediaVisibility::Public,
            MediaAsset::query()->findOrFail($fresh['id'])->visibility,
        );
    }

    public function test_pruning_withdraws_an_edit_screen_upload_that_was_never_saved(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $admin = User::factory()->administrator()->create();
        $article = NewsArticle::factory()->create(['body' => '<p>Tanpa gambar.</p>']);

        $abandoned = $this->actingAs($admin)
            ->postJson(route('admin.news.images.store-for-article', $article), [
                'image' => UploadedFile::fake()->image('tidak-disimpan.jpg'),
            ])
            ->assertCreated()
            ->json();

        $media = MediaAsset::query()->findOrFail($abandoned['id']);
        $this->assertSame(MediaVisibility::Public, $media->visibility);

        NewsArticleMedia::query()->where('media_asset_id', $media->id)->update([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->artisan('news:prune-staged-article-images')->assertSuccessful();

        $this->assertDatabaseMissing('news_article_media', [
            'news_article_id' => $article->id,
            'media_asset_id' => $media->id,
        ]);
        $this->assertSame(MediaVisibility::Private, $media->refresh()->visibility);
        Storage::disk('public')->assertMissing($media->public_object_key);
    }
}

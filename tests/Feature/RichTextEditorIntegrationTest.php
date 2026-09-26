<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\User;
use App\Services\RichTextSanitizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The rich text surface and the markup contract that keeps the article body
 * safe.
 *
 * The browser half of the editor (CKEditor 5 mount, toolbar, image upload) is
 * verified separately; what these cases pin down is the contract the server
 * still owns after the CKEditor migration: the textarea stays the submitted
 * field, the upload endpoint stays the only way an image gets in, and the
 * sanitizer still runs on write and on read.
 */
class RichTextEditorIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    public function test_the_news_form_ships_a_real_editor_surface_and_keeps_the_textarea(): void
    {
        $this->actingAs($this->admin())->get(route('admin.news.create'))
            ->assertOk()
            // The editor mount point and its data contract.
            ->assertSee('data-rich-text-editor', false)
            ->assertSee('data-rte-input', false)
            ->assertSee('data-rte-count', false)
            ->assertSee('data-rte-status', false)
            // The upload endpoint the editor adapter posts to.
            ->assertSee(route('admin.news.images.store'), false)
            // The no-JavaScript fallback is a real, labelled textarea.
            ->assertSee('<textarea', false)
            ->assertSee('name="body"', false)
            ->assertSee('Tulis isi berita di sini…')
            // The CKEditor bundle is only requested on demand, so the entry
            // chunk does not inline it.
            ->assertSee('/build/assets/app-', false);
    }

    public function test_the_placeholder_and_upload_limits_are_rendered(): void
    {
        $this->actingAs($this->admin())->get(route('admin.news.create'))
            ->assertOk()
            ->assertSee('data-placeholder="Tulis isi berita di sini…"', false)
            ->assertSee('Maks. 5 MB per gambar', false)
            ->assertSee('JPG, PNG, WEBP', false);
    }

    public function test_the_editor_module_is_loaded_through_a_dynamic_import(): void
    {
        $this->assertStringContainsString(
            "import('./editor/rich-text-editor')",
            file_get_contents(resource_path('js/app.js')),
            'The editor must stay out of the entry chunk.',
        );

        $this->assertStringContainsString(
            "import { normalizeForStorage } from './normalize-rich-text';",
            file_get_contents(resource_path('js/editor/rich-text-editor.js')),
        );
    }

    public function test_the_obsolete_editor_implementation_is_gone(): void
    {
        $package = json_decode(file_get_contents(base_path('package.json')), associative: true);

        $this->assertArrayHasKey('ckeditor5', $package['dependencies']);
        $this->assertArrayNotHasKey('@tiptap/core', $package['dependencies']);

        foreach (['subjects', 'image', 'placeholder', 'textAlign', 'starterKit'] as $tiptapPackage) {
            $this->assertArrayNotHasKey(
                '@tiptap/'.$tiptapPackage,
                $package['dependencies'],
                'The Tiptap package must not remain after the CKEditor migration.',
            );
        }

        $this->assertFileDoesNotExist(resource_path('views/components/form/editor-icon.blade.php'));
    }

    public function test_the_sanitizer_keeps_the_ckeditor_output_shape(): void
    {
        // The shape normalizeForStorage() produces for a CKEditor document.
        $stored = '<h2>Judul</h2>'
            .'<p data-align="center">Paragraf <strong>tebal</strong> <em>miring</em> <u>garis bawah</u> <s>coret</s></p>'
            .'<ul><li>Satu<ul><li>Nested</li></ul></li></ul>'
            .'<ol><li>Pertama</li></ol>'
            .'<blockquote><p>Kutipan</p></blockquote>'
            .'<hr><br>'
            .'<a href="https://sekolah.sch.id" rel="noopener noreferrer nofollow ugc">tautan</a>';

        $sanitized = (new RichTextSanitizer)->sanitize($stored);

        $this->assertStringContainsString('<h2>Judul</h2>', $sanitized);
        $this->assertStringContainsString('data-align="center"', $sanitized);
        $this->assertStringContainsString('<strong>tebal</strong>', $sanitized);
        $this->assertStringContainsString('<em>miring</em>', $sanitized);
        $this->assertStringContainsString('<u>garis bawah</u>', $sanitized);
        $this->assertStringContainsString('<s>coret</s>', $sanitized);
        $this->assertStringContainsString('<ul><li>Satu<ul><li>Nested</li></ul></li></ul>', $sanitized);
        $this->assertStringContainsString('<ol><li>Pertama</li></ol>', $sanitized);
        $this->assertStringContainsString('<blockquote><p>Kutipan</p></blockquote>', $sanitized);
        $this->assertStringContainsString('<hr', $sanitized);
        $this->assertStringNotContainsString('style=', $sanitized);
    }

    public function test_an_article_saved_from_the_editor_never_keeps_inline_styles_or_scripts(): void
    {
        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title' => 'Artikel dari editor',
            'slug' => 'artikel-dari-editor',
            'excerpt' => 'Ringkasan.',
            // The pre-normalisation shape, in case normalisation is ever skipped.
            'body' => '<p style="text-align: center; color: red;" onclick="steal()">Teks</p>'
                .'<figure class="image"><figcaption class="image__caption">Keterangan</figcaption></figure>'
                .'<script>alert(1)</script>',
            'privacy_review_confirmed' => '1',
        ])->assertRedirect(route('admin.news.index'))->assertSessionHasNoErrors();

        $body = NewsArticle::query()->where('slug', 'artikel-dari-editor')->firstOrFail()->body;

        $this->assertStringNotContainsString('style=', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringNotContainsString('class=', $body);
        $this->assertStringContainsString('Keterangan', $body);
    }

    public function test_a_plain_text_body_is_preserved_as_plain_text(): void
    {
        $body = "Baris pertama.\nBaris kedua.";

        $this->actingAs($this->admin())->post(route('admin.news.store'), [
            'title' => 'Artikel teks',
            'slug' => 'artikel-teks',
            'excerpt' => 'Ringkasan.',
            'body' => $body,
        ])->assertRedirect(route('admin.news.index'))->assertSessionHasNoErrors();

        $this->assertSame($body, NewsArticle::query()->where('slug', 'artikel-teks')->firstOrFail()->body);
    }

    public function test_only_administrators_can_reach_the_editor_upload_endpoint(): void
    {
        foreach ([User::factory()->guru()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)
                ->postJson(route('admin.news.images.store'), ['image' => UploadedFile::fake()->image('x.jpg')])
                ->assertForbidden();
        }
    }
}

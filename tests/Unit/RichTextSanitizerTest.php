<?php

namespace Tests\Unit;

use App\Services\RichTextImageSourcePolicy;
use App\Services\RichTextSanitizer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RichTextSanitizerTest extends TestCase
{
    private RichTextSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new RichTextSanitizer;
    }

    public function test_allowed_document_structure_survives(): void
    {
        $html = '<h2 data-align="center">Judul</h2>'
            .'<p>Paragraf dengan <strong>tebal</strong>, <em>miring</em>, <u>garis bawah</u>, dan <s>coret</s>.</p>'
            .'<blockquote>Kutipan</blockquote><hr><p>Setelah garis pemisah.</p>';

        $sanitized = $this->sanitizer->sanitize($html, RichTextImageSourcePolicy::publicMediaObjectKeys());

        $this->assertStringContainsString('<h2 data-align="center">Judul</h2>', $sanitized);
        $this->assertStringContainsString('<strong>tebal</strong>', $sanitized);
        $this->assertStringContainsString('<em>miring</em>', $sanitized);
        $this->assertStringContainsString('<u>garis bawah</u>', $sanitized);
        $this->assertStringContainsString('<s>coret</s>', $sanitized);
        $this->assertStringContainsString('<blockquote>Kutipan</blockquote>', $sanitized);
        $this->assertStringContainsString('<hr', $sanitized);
    }

    public function test_allowed_lists_including_nesting_survive(): void
    {
        $html = '<ul><li>Satu<ul><li>Dua</li></ul></li></ul><ol><li>Pertama</li><li>Kedua</li></ol>';

        $sanitized = $this->sanitizer->sanitize($html, RichTextImageSourcePolicy::publicMediaObjectKeys());

        $this->assertStringContainsString('<ul><li>Satu<ul><li>Dua</li></ul></li></ul>', $sanitized);
        $this->assertStringContainsString('<ol><li>Pertama</li><li>Kedua</li></ol>', $sanitized);
    }

    public function test_script_tags_are_removed_with_their_content(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            '<p>Sebelum</p><script>alert("xss")</script><p>Sesudah</p>',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertStringNotContainsString('script', $sanitized);
        $this->assertStringNotContainsString('alert', $sanitized);
        $this->assertStringContainsString('<p>Sebelum</p>', $sanitized);
        $this->assertStringContainsString('<p>Sesudah</p>', $sanitized);
    }

    public function test_inline_event_handlers_and_style_attributes_are_removed(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            '<p onclick="steal()" onmouseover="steal()" style="position:fixed;top:0">Teks</p>'
            .'<img src="https://localhost/storage/media/6b6b1c34-2a19-4b0e-9d3a-6a1d9a0f7a11.jpg" onerror="steal()" style="width:1px">',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertStringNotContainsString('onclick', $sanitized);
        $this->assertStringNotContainsString('onmouseover', $sanitized);
        $this->assertStringNotContainsString('onerror', $sanitized);
        $this->assertStringNotContainsString('style', $sanitized);
        $this->assertStringContainsString('<p>Teks</p>', $sanitized);
    }

    public function test_dangerous_uri_schemes_are_rejected_on_links(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            '<a href="javascript:alert(1)">satu</a><a href="JaVaScRiPt:alert(2)">dua</a>'
            .'<a href="data:text/html;base64,PHNjcmlwdD4=">tiga</a>',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertStringNotContainsString('href', $sanitized);
        $this->assertStringNotContainsString('javascript', strtolower($sanitized));
        $this->assertStringNotContainsString('data:text/html', $sanitized);
        $this->assertStringContainsString('satu', $sanitized);
        $this->assertStringContainsString('dua', $sanitized);
    }

    public function test_safe_links_survive_with_a_forced_rel_attribute(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            '<a href="https://sekolah.sch.id/berita" title="Sumber">sumber</a>',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertStringContainsString('href="https://sekolah.sch.id/berita"', $sanitized);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow ugc"', $sanitized);
    }

    public function test_embeds_and_forms_are_removed(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            '<iframe src="https://evil.test"></iframe><object data="x"></object><embed src="y">'
            .'<form action="/x"><input name="y"></form><svg><script>alert(1)</script></svg>',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertSame('', $sanitized);
    }

    public function test_inline_style_never_reaches_the_stored_body(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            '<p style="text-align: center;text-decoration: underline">Teks</p>',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertStringNotContainsString('style', $sanitized);
        $this->assertStringNotContainsString('text-align', $sanitized);
    }

    public function test_allowed_images_survive_with_their_descriptive_attributes(): void
    {
        $objectKey = 'media/6b6b1c34-2a19-4b0e-9d3a-6a1d9a0f7a11.jpg';
        $sanitized = $this->sanitizer->sanitize(
            '<img src="https://localhost/storage/media/6b6b1c34-2a19-4b0e-9d3a-6a1d9a0f7a11.jpg"'
            .' alt="Suasana sekolah" title="Kegiatan" data-align="center" data-width="75">',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertStringContainsString('src="'.Storage::disk('public')->url($objectKey).'"', $sanitized);
        $this->assertStringContainsString('alt="Suasana sekolah"', $sanitized);
        $this->assertStringContainsString('title="Kegiatan"', $sanitized);
        $this->assertStringContainsString('data-align="center"', $sanitized);
        $this->assertStringContainsString('data-width="75"', $sanitized);
    }

    public function test_data_urls_and_unapproved_image_sources_are_removed(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            '<p><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUg==" alt="a">'
            .'<img src="https://penacak.example.test/tracker.gif" alt="b"></p>',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertStringNotContainsString('base64', $sanitized);
        $this->assertStringNotContainsString('tracker.gif', $sanitized);
    }

    public function test_image_sources_are_limited_to_the_allowlist(): void
    {
        $policy = RichTextImageSourcePolicy::onlyObjectKeys([
            'media/6b6b1c34-2a19-4b0e-9d3a-6a1d9a0f7a11.jpg',
        ]);

        $sanitized = $this->sanitizer->sanitize(
            '<img src="https://localhost/storage/media/6b6b1c34-2a19-4b0e-9d3a-6a1d9a0f7a11.jpg">'
            .'<img src="https://localhost/storage/media/11111111-1111-1111-1111-111111111111.png">',
            $policy,
        );

        $this->assertStringContainsString('6b6b1c34-2a19-4b0e-9d3a-6a1d9a0f7a11.jpg', $sanitized);
        $this->assertStringNotContainsString('11111111-1111-1111-1111-111111111111', $sanitized);
    }

    public function test_a_rewritten_host_cannot_smuggle_a_foreign_image(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            '<img src="https://penacak.example.test/storage/media/6b6b1c34-2a19-4b0e-9d3a-6a1d9a0f7a11.jpg">',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertStringNotContainsString('penacak.example.test', $sanitized);
        $this->assertStringContainsString(
            'src="'.Storage::disk('public')->url('media/6b6b1c34-2a19-4b0e-9d3a-6a1d9a0f7a11.jpg').'"',
            $sanitized,
        );
    }

    public function test_images_are_denied_when_no_source_is_permitted(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            '<p>Teks</p><img src="https://localhost/storage/media/6b6b1c34-2a19-4b0e-9d3a-6a1d9a0f7a11.jpg">',
        );

        $this->assertSame('<p>Teks</p>', $sanitized);
    }

    public function test_unknown_alignment_and_size_values_are_dropped(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            '<p data-align="expression(alert(1))">a</p><img src="https://localhost/storage/media/6b6b1c34-2a19-4b0e-9d3a-6a1d9a0f7a11.jpg" data-width="9000">',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertStringNotContainsString('expression', $sanitized);
        $this->assertStringNotContainsString('data-width', $sanitized);
    }

    public function test_harmless_but_disallowed_markup_keeps_its_text(): void
    {
        $sanitized = $this->sanitizer->sanitize(
            'Teks <b>tebal</b> dan <div>blok</div> lama',
            RichTextImageSourcePolicy::publicMediaObjectKeys(),
        );

        $this->assertStringContainsString('tebal', $sanitized);
        $this->assertStringContainsString('blok', $sanitized);
        $this->assertStringNotContainsString('<b>', $sanitized);
        $this->assertStringNotContainsString('<div>', $sanitized);
    }

    public function test_rich_text_detection_only_matches_block_level_editor_markup(): void
    {
        $this->assertTrue(RichTextSanitizer::looksLikeRichText('<p>Paragraf</p>'));
        $this->assertTrue(RichTextSanitizer::looksLikeRichText('<h2>Judul</h2><ul><li>a</li></ul>'));
        $this->assertTrue(RichTextSanitizer::looksLikeRichText('<img src="x" >'));

        // Legacy bodies stay plain text so they keep rendering unchanged.
        $this->assertFalse(RichTextSanitizer::looksLikeRichText('Isi kabar sekolah.'));
        $this->assertFalse(RichTextSanitizer::looksLikeRichText("Baris satu\nBaris dua"));
        $this->assertFalse(RichTextSanitizer::looksLikeRichText('Jumlah 5 < 10 siswa'));
        $this->assertFalse(RichTextSanitizer::looksLikeRichText('<script>alert(1)</script>'));
        $this->assertFalse(RichTextSanitizer::looksLikeRichText(null));
    }
}

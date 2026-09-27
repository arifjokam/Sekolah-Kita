<?php

namespace Tests\Feature;

use App\Models\SchoolProfile;
use App\Models\User;
use App\Services\RichTextImageSourcePolicy;
use App\Services\RichTextSanitizer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The school profile's long-form copy is a genuine rich text surface.
 *
 * Vision, mission and about are written by an administrator and read on the
 * public site, so they need real formatting. The safety rules are the same ones
 * the news body follows: sanitize on write, sanitize again on read, and never
 * allow an image source, because the profile editor has no image pipeline.
 */
class SchoolProfileRichTextTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'SMP Negeri Uji',
            'public_name' => 'SMP Uji',
            'short_name' => 'SMP Uji',
            'address_line' => 'Jl. Uji No. 1',
            'city' => 'Bandung',
            'postal_code' => '40115',
            'public_phone' => '022-1234567',
            'public_email' => 'info@smpuji.test',
            'vision' => '<p>Menjadi sekolah <strong>unggul</strong>.</p>',
            'mission' => '<p>Menyelenggarakan pembelajaran yang bermutu.</p>',
            'about' => '<p>Profil sekolah kami.</p>',
        ], $overrides);
    }

    public function test_the_profile_form_mounts_a_rich_text_editor_for_each_long_form_field(): void
    {
        $this->actingAs($this->admin())->get(route('admin.school-profile.edit'))
            ->assertOk()
            ->assertSee('data-rich-text-editor', false)
            ->assertSee('name="about"', false)
            ->assertSee('name="vision"', false)
            ->assertSee('name="mission"', false);
    }

    public function test_the_profile_editor_offers_no_image_button_because_there_is_no_upload_route(): void
    {
        // data-upload-url is what makes the image buttons appear, and the profile
        // has no image endpoint, so the attribute must be absent.
        $html = $this->actingAs($this->admin())
            ->get(route('admin.school-profile.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-upload-url', $html);
    }

    public function test_rich_text_copy_is_sanitized_on_write(): void
    {
        $this->actingAs($this->admin())->put(route('admin.school-profile.update'), $this->payload([
            'about' => '<p> Aman <script>alert(1)</script><span onclick="x()">teks</span>.</p>',
        ]))->assertRedirect(route('admin.school-profile.edit'));

        $about = SchoolProfile::query()->where('is_singleton', true)->value('about');

        $this->assertStringNotContainsString('<script', (string) $about);
        $this->assertStringNotContainsString('onclick', (string) $about);
        $this->assertStringContainsString('Aman', (string) $about);
    }

    public function test_a_profile_image_source_is_refused_because_the_editor_has_no_image_pipeline(): void
    {
        $this->actingAs($this->admin())->put(route('admin.school-profile.update'), $this->payload([
            'about' => '<p>Gambar <img src="https://evil.test/x.png" alt="x"> sekolah.</p>',
        ]))->assertRedirect(route('admin.school-profile.edit'));

        $about = (string) SchoolProfile::query()->where('is_singleton', true)->value('about');

        $this->assertStringNotContainsString('<img', $about);
        $this->assertStringNotContainsString('evil.test', $about);
    }

    public function test_plain_text_copy_stays_plain_text_and_keeps_its_line_breaks(): void
    {
        // A profile written before the editor existed must keep rendering as it
        // did, so plain copy is stored verbatim rather than being rewritten.
        $this->actingAs($this->admin())->put(route('admin.school-profile.update'), $this->payload([
            'vision' => "Baris pertama\nBaris kedua",
        ]))->assertRedirect(route('admin.school-profile.edit'));

        $this->assertSame(
            "Baris pertama\nBaris kedua",
            SchoolProfile::query()->where('is_singleton', true)->value('vision')
        );
    }

    public function test_the_public_profile_renders_rich_text_as_markup(): void
    {
        $this->actingAs($this->admin())->put(route('admin.school-profile.update'), $this->payload([
            'vision' => '<p>Visi <strong>kuat</strong>.</p>',
        ]))->assertRedirect(route('admin.school-profile.edit'));

        $this->get(route('school-profile.show'))
            ->assertOk()
            ->assertSee('Visi <strong>kuat</strong>.', false);
    }

    public function test_the_public_profile_still_escapes_plain_text_copy(): void
    {
        $this->actingAs($this->admin())->put(route('admin.school-profile.update'), $this->payload([
            'mission' => 'Misi <b>plain</b> & sederhana',
        ]))->assertRedirect(route('admin.school-profile.edit'));

        $this->get(route('school-profile.show'))
            ->assertOk()
            ->assertSee('Misi &lt;b&gt;plain&lt;/b&gt; &amp; sederhana', false);
    }

    public function test_the_public_profile_sanitizes_again_on_read(): void
    {
        // Written straight to the model, bypassing the form: the read path still
        // has to refuse a script.
        SchoolProfile::query()->create([
            'is_singleton' => true,
            'legal_name' => 'SMP Uji',
            'public_name' => 'SMP Uji',
            'vision' => '<p>Script <script>alert(1)</script>hai</p>',
            'mission' => '<p>Misi</p>',
            'about' => '<p>About</p>',
        ]);

        $response = $this->get(route('school-profile.show'))->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $this->assertStringContainsString('Script', $response->getContent());
    }

    public function test_the_homepage_teaser_reduces_rich_text_to_plain_text(): void
    {
        SchoolProfile::query()->create([
            'is_singleton' => true,
            'legal_name' => 'SMP Uji',
            'public_name' => 'SMP Uji',
            'vision' => '<p>Visi</p>',
            'mission' => '<p>Misi</p>',
            'about' => '<p>Tentang <strong>sekolah</strong> kami.</p>',
        ]);

        $response = $this->get(route('home'))->assertOk();
        $html = $response->getContent();

        // The teaser is one line inside a <p>, so the markup is reduced to its
        // text rather than escaped and shown to the visitor as literal tags.
        $this->assertStringContainsString('Tentang sekolah kami.', $html);
        $this->assertStringNotContainsString('<p>Tentang', $html);
    }

    public function test_the_public_footer_teaser_also_stays_plain_text(): void
    {
        SchoolProfile::query()->create([
            'is_singleton' => true,
            'legal_name' => 'SMP Uji',
            'public_name' => 'SMP Uji',
            'vision' => '<p>Visi</p>',
            'mission' => '<p>Misi</p>',
            'about' => '<p>Sekolahmie <em>unggul</em> di Bandung.</p>',
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Sekolahmie unggul di Bandung.', $html);
    }

    public function test_a_plain_text_teaser_is_passed_through_unchanged(): void
    {
        SchoolProfile::query()->create([
            'is_singleton' => true,
            'legal_name' => 'SMP Uji',
            'public_name' => 'SMP Uji',
            'vision' => 'Visi sederhana',
            'mission' => 'Misi sederhana',
            'about' => 'Tentang sekolah kami',
        ]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Tentang sekolah kami', $html);
    }

    public function test_a_guru_cannot_edit_the_school_profile(): void
    {
        $this->actingAs(User::factory()->guru()->create())
            ->get(route('admin.school-profile.edit'))
            ->assertForbidden();
    }

    public function test_the_sanitizer_used_for_the_profile_denies_every_image_source(): void
    {
        // The profile read path is configured with denyAll, which the browser
        // suite relies on when it asserts the profile editors expose no image
        // button.
        $sanitizer = app(RichTextSanitizer::class);

        $this->assertSame(
            '',
            $sanitizer->sanitize('<img src="https://example.test/a.png" alt="a">', RichTextImageSourcePolicy::denyAll())
        );
    }
}

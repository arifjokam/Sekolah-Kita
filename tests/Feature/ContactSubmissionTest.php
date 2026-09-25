<?php

namespace Tests\Feature;

use App\Enums\ContactMessageStatus;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ContactSubmissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_contact_submission_is_validated_normalized_and_safely_stored(): void
    {
        Log::spy();

        $this->post(route('contact.store'), [
            'name' => '  Pengirim  ',
            'email' => '  PERSON@EXAMPLE.TEST ',
            'phone' => '08123456789',
            'subject' => '  Pertanyaan  ',
            'message' => '  Halo sekolah  ',
        ])->assertRedirect(route('contact.create'))->assertSessionHas('contact_success');

        $message = ContactMessage::query()->firstOrFail();
        $this->assertSame('Pengirim', $message->name);
        $this->assertSame('person@example.test', $message->email);
        $this->assertSame(ContactMessageStatus::New, $message->status);
        Log::shouldHaveReceived('info')->withArgs(function (string $event, array $context): bool {
            return $event === 'Public contact message received.'
                && ! in_array('person@example.test', $context, true)
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'Halo sekolah');
        });
    }

    public function test_contact_requires_a_way_to_reply_and_rejects_honeypot(): void
    {
        $this->from(route('contact.create'))->post(route('contact.store'), [
            'name' => 'Tanpa kontak',
            'subject' => 'Halo',
            'message' => 'Pesan',
        ])->assertSessionHasErrors('contact');

        $this->from(route('contact.create'))->post(route('contact.store'), [
            'name' => 'Bot',
            'email' => 'bot@example.test',
            'subject' => 'Halo',
            'message' => 'Pesan',
            'website' => 'https://spam.invalid',
        ])->assertSessionHasErrors('website');
    }

    public function test_contact_submission_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('contact.store'), [
                'name' => 'Pengirim '.$attempt,
                'email' => 'same@example.test',
                'subject' => 'Halo',
                'message' => 'Pesan',
            ])->assertRedirect();
        }

        $this->post(route('contact.store'), [
            'name' => 'Pengirim keenam',
            'email' => 'same@example.test',
            'subject' => 'Halo',
            'message' => 'Pesan',
        ])->assertTooManyRequests();
    }

    public function test_contact_messages_are_visible_only_to_administrators(): void
    {
        $message = ContactMessage::factory()->create(['message' => 'Private contact message']);
        $admin = User::factory()->administrator()->create();
        $guru = User::factory()->guru()->create();

        $this->actingAs($guru)->get(route('admin.contact-messages.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.contact-messages.show', $message))->assertOk()->assertSee('Private contact message');
    }

    public function test_contact_status_update_is_restricted_and_sets_response_time(): void
    {
        $message = ContactMessage::factory()->create();
        $admin = User::factory()->administrator()->create();
        $siswa = User::factory()->create();

        $this->actingAs($siswa)->patch(route('admin.contact-messages.update', $message), ['status' => 'responded'])->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.contact-messages.update', $message), ['status' => 'responded'])->assertRedirect();
        $this->assertSame(ContactMessageStatus::Responded, $message->refresh()->status);
        $this->assertNotNull($message->responded_at);
    }
}

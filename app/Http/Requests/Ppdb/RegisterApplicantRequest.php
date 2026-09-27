<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Creating a PPDB applicant portal account.
 *
 * `authorize()` is intentionally permissive for the guest route: the whole point
 * of the screen is to be reachable by someone with no account, and there is
 * nothing to authorise. The protections that matter are on the rules: a unique
 * address, a strong password, and a honeypot.
 */
class RegisterApplicantRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email:rfc', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
            'whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
            'password' => ['required', 'confirmed', Password::min(12)->max(255)],
            'consent' => ['accepted'],
            // Honeypot: a real browser leaves this hidden field empty.
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->indonesianMessages(), [
            'consent.accepted' => 'Anda harus menyetujui pernyataan pemrosesan data pendaftaran.',
            'website.prohibited' => 'Pengajuan ditolak.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, dan tanda + - ( ).',
            'whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, dan tanda + - ( ).',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama lengkap',
            'email' => 'surel',
            'phone' => 'nomor telepon',
            'whatsapp' => 'nomor WhatsApp',
            'password' => 'kata sandi',
            'consent' => 'persetujuan data',
        ];
    }
}

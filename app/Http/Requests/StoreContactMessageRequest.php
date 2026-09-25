<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'name' => Str::trim((string) $this->input('name')),
            'email' => is_string($email) ? Str::lower(trim($email)) : $email,
            'phone' => Str::trim((string) $this->input('phone')),
            'subject' => Str::trim((string) $this->input('subject')),
            'message' => Str::trim((string) $this->input('message')),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]+$/'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['name', 'email', 'phone', 'subject', 'message'])) {
                    return;
                }

                if (! $this->filled('email') && ! $this->filled('phone')) {
                    $validator->errors()->add('contact', 'Email atau nomor telepon wajib diisi.');
                }
            },
        ];
    }
}

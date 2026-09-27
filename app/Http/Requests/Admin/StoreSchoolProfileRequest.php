<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSchoolProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $profile = SchoolProfile::query()->where('is_singleton', true)->first();

        return $profile === null
            ? Gate::allows('create', SchoolProfile::class)
            : Gate::allows('update', $profile);
    }

    /**
     * Normalize the removal checkboxes so the service layer always receives real booleans.
     */
    protected function prepareForValidation(): void
    {
        foreach (['remove_logo', 'remove_hero'] as $field) {
            if ($this->exists($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:150'],
            'public_name' => ['nullable', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'public_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+() .-]+$/'],
            'public_email' => ['nullable', 'email:rfc', 'max:150'],
            // The long-form fields hold editor markup, so the character cap is
            // the domain's own limit rather than a validation maximum; the
            // storage ceiling is enforced in SchoolProfileContentService so a
            // rejected body reports a storage message instead of a length one.
            'vision' => ['nullable', 'string', 'max:20000'],
            'mission' => ['nullable', 'string', 'max:20000'],
            'about' => ['nullable', 'string', 'max:60000'],
            'logo_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1,min_height=1,max_width=6000,max_height=6000'],
            'hero_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1,min_height=1,max_width=6000,max_height=6000'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_hero' => ['nullable', 'boolean'],
            'privacy_review_confirmed' => Rule::when(
                $this->hasFile('logo_image') || $this->hasFile('hero_image'),
                ['accepted'],
            ),
        ];
    }
}

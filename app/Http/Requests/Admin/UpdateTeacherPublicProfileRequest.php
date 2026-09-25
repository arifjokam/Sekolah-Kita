<?php

namespace App\Http\Requests\Admin;

use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTeacherPublicProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('managePublicProfile', $this->route('teacher'));
    }

    /**
     * Normalize the removal checkbox so the service layer always receives a real boolean.
     */
    protected function prepareForValidation(): void
    {
        if ($this->exists('remove_photo')) {
            $this->merge(['remove_photo' => $this->boolean('remove_photo')]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Teacher $teacher */
        $teacher = $this->route('teacher');

        return [
            'directory_slug' => [
                'required',
                'string',
                'max:180',
                'alpha_dash',
                Rule::unique('teachers', 'directory_slug')->ignore($teacher),
            ],
            'position_title' => ['nullable', 'string', 'max:100'],
            'biography' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1,min_height=1,max_width=6000,max_height=6000'],
            'remove_photo' => ['nullable', 'boolean'],
            'privacy_review_confirmed' => Rule::when(
                $this->hasFile('photo'),
                ['accepted'],
            ),
        ];
    }
}

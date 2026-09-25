<?php

namespace App\Http\Requests\Admin;

use App\Enums\PublicationStatus;
use App\Models\Achievement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateAchievementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('achievement'));
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('title')) {
            $this->merge(['slug' => Str::slug($this->string('title')->toString())]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var Achievement $achievement */
        $achievement = $this->route('achievement');

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required',
                'string',
                'max:220',
                'alpha_dash',
                Rule::unique('achievements', 'slug')->ignore($achievement),
            ],
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'max:100'],
            'level' => ['required', 'string', 'max:100'],
            'achievement_date' => [
                'required',
                'date_format:Y-m-d',
                Rule::when(
                    $achievement->status === PublicationStatus::Published,
                    ['before_or_equal:today'],
                ),
            ],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1,min_height=1,max_width=6000,max_height=6000'],
            'privacy_review_confirmed' => ['required', 'accepted'],
        ];
    }
}

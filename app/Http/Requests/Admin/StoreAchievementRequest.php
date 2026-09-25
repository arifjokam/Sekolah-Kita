<?php

namespace App\Http\Requests\Admin;

use App\Models\Achievement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreAchievementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Achievement::class);
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
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:220', 'alpha_dash', 'unique:achievements,slug'],
            'description' => ['required', 'string', 'max:10000'],
            'category' => ['required', 'string', 'max:100'],
            'level' => ['required', 'string', 'max:100'],
            'achievement_date' => ['required', 'date_format:Y-m-d'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1,min_height=1,max_width=6000,max_height=6000'],
            'privacy_review_confirmed' => ['required', 'accepted'],
        ];
    }
}

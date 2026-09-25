<?php

namespace App\Http\Requests\Admin;

use App\Models\GalleryAlbum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class StoreGalleryAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', GalleryAlbum::class);
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
            'slug' => ['required', 'string', 'max:220', 'alpha_dash', 'unique:gallery_albums,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

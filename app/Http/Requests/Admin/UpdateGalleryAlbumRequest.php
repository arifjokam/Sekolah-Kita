<?php

namespace App\Http\Requests\Admin;

use App\Models\GalleryAlbum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateGalleryAlbumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('galleryAlbum'));
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
        /** @var GalleryAlbum $album */
        $album = $this->route('galleryAlbum');

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => [
                'required',
                'string',
                'max:220',
                'alpha_dash',
                Rule::unique('gallery_albums', 'slug')->ignore($album),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateGalleryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('galleryItem'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['required', 'string', 'max:255'],
            'event_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1,min_height=1,max_width=6000,max_height=6000'],
            'privacy_review_confirmed' => ['required', 'accepted'],
        ];
    }
}

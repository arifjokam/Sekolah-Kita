<?php

namespace App\Http\Requests\Admin;

use App\Models\MediaAsset;
use App\Services\MediaAssetService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Validates an image uploaded from the article rich text editor.
 *
 * The rules mirror the cover image rules. The declared extension, the sniffed
 * MIME type, the real image header and the re-encode in MediaAssetService all
 * have to agree before a byte reaches storage, so none of them can be trusted
 * on its own.
 */
class UploadNewsArticleImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', MediaAsset::class);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:'.intdiv(MediaAssetService::MAX_IMAGE_BYTES, 1024),
                'dimensions:min_width=1,min_height=1,max_width=6000,max_height=6000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'image' => 'gambar',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactMessageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateContactMessageStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateStatus', $this->route('contactMessage'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ContactMessageStatus::class)],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'category' => ['required', Rule::enum(NotificationCategory::class)],
            'enabled' => ['required', 'boolean'],
        ];
    }
}

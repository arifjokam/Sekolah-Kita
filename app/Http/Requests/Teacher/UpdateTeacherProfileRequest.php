<?php

namespace App\Http\Requests\Teacher;

use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service edits a teacher is allowed to make to their own record.
 *
 * Deliberately narrow: only the contact fields. NIP, NUPTK, employment status,
 * position and the public-directory fields stay administrator-managed, because
 * they are the records a school is formally accountable for.
 */
class UpdateTeacherProfileRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        $teacher = $this->user()?->teacher;

        return $teacher !== null && $teacher->user_id === $this->user()->getKey();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->indonesianMessages(), [
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, dan tanda + - ( ).',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'phone' => 'nomor telepon',
            'address' => 'alamat',
        ];
    }
}

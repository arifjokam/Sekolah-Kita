<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service edits a student is allowed to make to their own record.
 *
 * Only contact fields. NIS, NISN, NIK, birth data, lifecycle status and the
 * admission date remain administrator-managed, so a student cannot rewrite the
 * identity a school reports to the authorities.
 */
class UpdateStudentProfileRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        $student = $this->user()?->student;

        return $student !== null && $student->user_id === $this->user()->getKey();
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

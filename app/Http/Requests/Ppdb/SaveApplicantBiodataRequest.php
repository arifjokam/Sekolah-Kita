<?php

namespace App\Http\Requests\Ppdb;

use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\AdmissionApplication;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The student biodata section of a portal application.
 *
 * NIK is validated as exactly sixteen digits: that is the national identifier
 * format, and a wrong-length NIK would otherwise travel all the way into a
 * converted Student record before anyone noticed.
 */
class SaveApplicantBiodataRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        $application = $this->route('application');

        return $application instanceof AdmissionApplication
            && ($this->user()?->can('updateOwn', $application) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'applicant_name' => ['required', 'string', 'max:150'],
            'preferred_name' => ['nullable', 'string', 'max:150'],
            'national_id' => ['required', 'string', 'digits:16', 'max:32'],
            'nisn' => ['nullable', 'string', 'digits:10', 'max:20'],
            'birth_place' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today', 'after:2000-01-01'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'address' => ['required', 'string', 'max:500'],
            'address_rt' => ['nullable', 'string', 'max:10'],
            'address_rw' => ['nullable', 'string', 'max:10'],
            'village' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'regency' => ['required', 'string', 'max:120'],
            'province' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'digits:5', 'max:10'],
            'contact_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
            'contact_whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
            'current_school' => ['nullable', 'string', 'max:150'],
            'graduation_year' => ['nullable', 'integer', 'min:2000', 'max:'.(int) date('Y')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->indonesianMessages(), [
            'national_id.digits' => 'NIK harus terdiri dari 16 angka.',
            'nisn.digits' => 'NISN harus terdiri dari 10 angka.',
            'gender.in' => 'Jenis kelamin harus L atau P.',
            'contact_phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, dan tanda + - ( ).',
            'contact_whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, dan tanda + - ( ).',
            'birth_date.before' => 'Tanggal lahir tidak valid.',
            'birth_date.after' => 'Tanggal lahir tidak valid.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'applicant_name' => 'nama lengkap',
            'national_id' => 'NIK',
            'nisn' => 'NISN',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'gender' => 'jenis kelamin',
            'address' => 'alamat',
            'regency' => 'kabupaten/kota',
            'province' => 'provinsi',
            'postal_code' => 'kode pos',
            'contact_phone' => 'nomor telepon',
            'contact_whatsapp' => 'nomor WhatsApp',
            'current_school' => 'sekolah asal',
            'graduation_year' => 'tahun lulus',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('birth_date') && $this->filled('graduation_year')
                    && (int) $this->input('graduation_year') < (int) date('Y', strtotime((string) $this->input('birth_date'))) + 6) {
                    $validator->errors()->add('graduation_year', 'Tahun lulus tidak konsisten dengan tanggal lahir.');
                }
            },
        ];
    }
}

<?php

namespace App\Http\Requests\Ppdb;

use App\Enums\GuardianRelationshipType;
use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\AdmissionApplication;
use App\Models\ApplicationGuardian;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A guardian (father, mother or legal guardian) on a portal application.
 *
 * One request serves both create and update; `authorize()` resolves the right
 * ability from whichever guardian the route carries.
 */
class SaveApplicantGuardianRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $guardian = $this->route('guardian');

        if ($guardian instanceof ApplicationGuardian) {
            // The guardian and the application are both route parameters, so
            // they are compared here rather than trusted individually: a valid
            // guardian id paired with somebody else's application is a 403.
            $application = $this->route('application');

            if (! $application instanceof AdmissionApplication
                || (int) $guardian->admission_application_id !== (int) $application->getKey()) {
                return false;
            }

            return $user->can('update', $guardian);
        }

        $application = $this->route('application');

        return $application instanceof AdmissionApplication
            && $user->can('create', [ApplicationGuardian::class, $application]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:150'],
            'national_id' => ['required', 'string', 'digits:16', 'max:32'],
            'relationship_type' => ['required', Rule::enum(GuardianRelationshipType::class)],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:150'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_primary_contact' => ['boolean'],
            'is_emergency_contact' => ['boolean'],
            'priority_order' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->indonesianMessages(), [
            'national_id.digits' => 'NIK harus terdiri dari 16 angka.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, dan tanda + - ( ).',
            'whatsapp_number.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, dan tanda + - ( ).',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'legal_name' => 'nama wali',
            'national_id' => 'NIK wali',
            'relationship_type' => 'hubungan',
            'phone' => 'nomor telepon',
            'whatsapp_number' => 'nomor WhatsApp',
            'occupation' => 'pekerjaan',
            'address' => 'alamat',
        ];
    }
}

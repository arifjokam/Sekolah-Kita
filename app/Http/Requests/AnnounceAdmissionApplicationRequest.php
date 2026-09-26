<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\HasIndonesianValidationMessages;
use App\Models\AdmissionApplication;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A targeted PPDB announcement to one applicant.
 *
 * A message is only accepted when there is somewhere to send it: the applicant
 * portal number, or a guardian WhatsApp number. Without one, the request is
 * refused rather than silently accepted and then dropped, because an
 * announcement that reaches nobody is worse than an error message.
 */
class AnnounceAdmissionApplicationRequest extends FormRequest
{
    use HasIndonesianValidationMessages;

    public function authorize(): bool
    {
        $application = $this->route('admissionApplication');

        return $application instanceof AdmissionApplication
            && ($this->user()?->can('review', $application) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul pengumuman',
            'message' => 'isi pengumuman',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var AdmissionApplication $application */
                $application = $this->route('admissionApplication');

                $hasPortalAccount = $application->applicant_user_id !== null;
                $hasNumber = filled($application->contact_whatsapp) || filled($application->contact_phone);

                if (! $hasPortalAccount && ! $hasNumber) {
                    $validator->errors()->add(
                        'message',
                        'Tidak ada akun portal maupun nomor WhatsApp untuk pengajuan ini.',
                    );
                }
            },
        ];
    }
}

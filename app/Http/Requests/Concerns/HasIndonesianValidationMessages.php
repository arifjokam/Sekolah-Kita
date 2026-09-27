<?php

namespace App\Http\Requests\Concerns;

/**
 * Indonesian validation copy for the administrator master data forms.
 *
 * The application copy is Indonesian, so Laravel's built-in English rule
 * messages are the only foreign text a user can still meet in a form. The rule
 * set below covers every rule the master data requests use; anything outside
 * that set still falls back to the framework message rather than failing.
 */
trait HasIndonesianValidationMessages
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->indonesianMessages();
    }

    /**
     * The same message set, exposed so a request that needs to add a few
     * domain-specific messages can merge them instead of re-declaring
     * `messages()` and shadowing this one.
     *
     * @return array<string, string>
     */
    protected function indonesianMessages(): array
    {
        return [
            'required' => 'Kolom :attribute wajib diisi.',
            'required_if' => 'Kolom :attribute wajib diisi ketika :other bernilai :value.',
            'required_with' => 'Kolom :attribute wajib diisi bersama :values.',
            'nullable' => 'Kolom :attribute boleh dikosongkan.',
            'string' => 'Kolom :attribute harus berupa teks.',
            'integer' => 'Kolom :attribute harus berupa bilangan bulat.',
            'numeric' => 'Kolom :attribute harus berupa angka.',
            'boolean' => 'Kolom :attribute harus bernilai benar atau salah.',
            'array' => 'Kolom :attribute harus berupa daftar.',
            'date' => 'Kolom :attribute harus berupa tanggal yang valid.',
            'date_format' => 'Kolom :attribute harus berformat :format.',
            'email' => 'Kolom :attribute harus berupa alamat email yang valid.',
            'url' => 'Kolom :attribute harus berupa URL yang valid.',
            'min' => [
                'numeric' => 'Kolom :attribute minimal :min.',
                'file' => 'Ukuran :attribute minimal :min kilobyte.',
                'string' => 'Kolom :attribute minimal :min karakter.',
                'array' => 'Kolom :attribute minimal :min item.',
            ],
            'max' => [
                'numeric' => 'Kolom :attribute maksimal :max.',
                'file' => 'Ukuran :attribute maksimal :max kilobyte.',
                'string' => 'Kolom :attribute maksimal :max karakter.',
                'array' => 'Kolom :attribute maksimal :max item.',
            ],
            'size' => [
                'numeric' => 'Kolom :attribute harus berukuran :max.',
                'file' => 'Ukuran :attribute harus tidak lebih dari :max kilobyte.',
                'string' => 'Kolom :attribute harus berisi :max karakter.',
            ],
            'exists' => 'Pilihan :attribute tidak valid atau sudah tidak tersedia.',
            'unique' => 'Nilai :attribute sudah digunakan.',
            'in' => 'Pilihan :attribute tidak valid.',
            'regex' => 'Format :attribute tidak valid.',
            'alpha_dash' => 'Kolom :attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
            'after' => 'Kolom :attribute harus setelah :date.',
            'before' => 'Kolom :attribute harus sebelum :date.',
            'after_or_equal' => 'Kolom :attribute harus setelah atau sama dengan :date.',
            'before_or_equal' => 'Kolom :attribute harus sebelum atau sama dengan :date.',
            'accepted' => 'Kolom :attribute wajib dicentang.',
            'confirmed' => 'Konfirmasi :attribute tidak cocok.',
            'digits' => 'Kolom :attribute harus terdiri dari :digits angka.',
            'same' => 'Kolom :attribute harus sama dengan :other.',
        ];
    }
}

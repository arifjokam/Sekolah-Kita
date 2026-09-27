@extends('layouts.app')

@section('title', ($teacher ? 'Edit' : 'Tambah').' guru · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Guru', 'route' => 'admin.teachers.index'],
            ['label' => $teacher ? 'Edit' : 'Tambah'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$teacher ? 'Edit data guru' : 'Tambah guru'"
        :description="$teacher
            ? 'Perubahan disimpan ke record guru. Akun login tidak dibuat otomatis dari formulir ini.'
            : 'Record kepegawaian guru. Akun login dan penugasan mengajar disiapkan terpisah.'"
    />

    <x-form.error-summary />

    <form
        method="POST"
        action="{{ $teacher ? route('admin.teachers.update', $teacher) : route('admin.teachers.store') }}"
        class="space-y-6"
    >
        @csrf
        @if ($teacher) @method('PUT') @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="identity-heading">
                <h2 id="identity-heading" class="text-lg font-bold text-slate-950">Identitas</h2>

                <x-form.text name="legal_name" label="Nama lengkap" required :value="$teacher?->legal_name" maxlength="150" />
                <x-form.text name="preferred_name" label="Nama panggilan" :value="$teacher?->preferred_name" maxlength="150" hint="Dipakai pada sapaan di dashboard dan daftar kelas." />
                <x-form.text name="employee_number" label="Nomor induk pegawai" required :value="$teacher?->employee_number" maxlength="50" hint="Huruf, angka, titik, garis hubung, dan garis miring." />
                <x-form.text name="position_title" label="Jabatan" :value="$teacher?->position_title" maxlength="100" />
                <x-form.text name="national_id" label="Nomor identitas nasional" :value="$teacher?->national_id" maxlength="32" hint="8 sampai 32 digit. Data ini tidak pernah dipublikasikan." />
            </section>

            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="employment-heading">
                <h2 id="employment-heading" class="text-lg font-bold text-slate-950">Kepegawaian</h2>

                <x-form.select
                    name="employment_status"
                    label="Status kepegawaian"
                    required
                    :value="$teacher?->employment_status?->value ?? \App\Enums\TeacherEmploymentStatus::Active->value"
                    :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                />
                <x-form.date name="employment_start_date" label="Tanggal mulai kerja" required :value="$teacher?->employment_start_date" />
                <x-form.date name="employment_end_date" label="Tanggal akhir kerja" :value="$teacher?->employment_end_date" hint="Diisi otomatis saat status menjadi tidak aktif atau pensiun." />

                <div>
                    <label for="gender" class="block text-sm font-medium text-slate-700">Jenis kelamin</label>
                    <select id="gender" name="gender" class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm shadow-sm focus:border-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-600/30">
                        <option value="">Tidak diisi</option>
                        <option value="L" @selected(old('gender', $teacher?->gender) === 'L')>Laki-laki</option>
                        <option value="P" @selected(old('gender', $teacher?->gender) === 'P')>Perempuan</option>
                    </select>
                    <x-form.field-error name="gender" />
                </div>

                <x-form.date name="birth_date" label="Tanggal lahir" :value="$teacher?->birth_date" />
                <x-form.text name="birth_place" label="Tempat lahir" :value="$teacher?->birth_place" maxlength="100" />
            </section>

            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="contact-heading">
                <h2 id="contact-heading" class="text-lg font-bold text-slate-950">Kontak</h2>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.text name="email" label="Email" type="email" :value="$teacher?->email" maxlength="150" />
                    <x-form.text name="phone" label="Telepon" :value="$teacher?->phone" maxlength="30" />
                </div>

                <x-form.textarea name="address" label="Alamat" :value="$teacher?->address" :rows="3" maxlength="2000" />
            </section>

            <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="profile-heading">
                <h2 id="profile-heading" class="text-lg font-bold text-slate-950">Ringkasan profesional</h2>
                <p class="text-sm leading-6 text-slate-600">
                    Ringkasan kualifikasi tampil di direktori publik bila profil guru diterbitkan. Biografi publik diatur pada halaman Guru publik.
                </p>

                <x-form.textarea name="qualification_summary" label="Ringkasan kualifikasi" :value="$teacher?->qualification_summary" :rows="3" maxlength="2000" />
                <x-form.textarea name="biography" label="Biografi" :value="$teacher?->biography" :rows="6" maxlength="5000" />
            </section>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ $teacher ? route('admin.teachers.show', $teacher) : route('admin.teachers.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
            <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                {{ $teacher ? 'Simpan perubahan' : 'Simpan guru' }}
            </button>
        </div>
    </form>
@endsection

@extends('layouts.app')

@section('title', 'Biodata siswa · PPDB')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard pendaftar', 'route' => 'ppdb.portal.dashboard'],
            ['label' => 'Biodata siswa'],
        ];

        $editable = $progress['can_edit'];
    @endphp

    <x-admin.page-header
        eyebrow="Pendaftaran"
        title="Biodata siswa"
        description="Isi sesuai akta kelahiran. Bagian ini tersimpan sebagai draf dan dapat diperbaiki sampai pengajuan diajukan."
    />

    <x-form.error-summary />

    @unless ($editable)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900" role="status">
            Pengajuan Anda sudah diajukan atau sedang ditinjau, sehingga data ini tidak dapat diubah. Hubungi bagian
            tata usaha sekolah bila ada data yang keliru.
        </div>
    @endunless

    <form
        method="POST"
        action="{{ route('ppdb.portal.biodata.update', $application) }}"
        class="max-w-4xl space-y-6"
    >
        @csrf
        @method('PUT')

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="identity-heading">
            <h2 id="identity-heading" class="text-lg font-bold text-slate-950">Identitas</h2>

            <x-form.text name="applicant_name" label="Nama lengkap" required maxlength="150" :readonly="! $editable" :value="$application->applicant_name" />
            <x-form.text name="preferred_name" label="Nama panggilan" maxlength="150" :readonly="! $editable" :value="$application->preferred_name" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.text name="national_id" label="NIK" required inputmode="numeric" maxlength="16" :readonly="! $editable" :value="$application->national_id" hint="16 angka sesuai akta kelahiran." />
                <x-form.text name="nisn" label="NISN" inputmode="numeric" maxlength="10" :readonly="! $editable" :value="$application->nisn" hint="10 angka. Boleh dikosongkan bila belum memiliki." />
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.text name="birth_place" label="Tempat lahir" required maxlength="100" :readonly="! $editable" :value="$application->birth_place" />
                <x-form.date name="birth_date" label="Tanggal lahir" required max="now" :readonly="! $editable" :value="$application->birth_date" />
                <x-form.select
                    name="gender"
                    label="Jenis kelamin"
                    required
                    :readonly="! $editable"
                    :value="$application->gender"
                    :options="['L' => 'Laki-laki', 'P' => 'Perempuan']"
                />
            </div>
        </section>

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="address-heading">
            <h2 id="address-heading" class="text-lg font-bold text-slate-950">Alamat</h2>

            <x-form.textarea name="address" label="Alamat jalan" required rows="3" maxlength="500" :readonly="! $editable" :value="$application->address" />
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.text name="address_rt" label="RT" maxlength="10" :readonly="! $editable" :value="$application->address_rt" />
                <x-form.text name="address_rw" label="RW" maxlength="10" :readonly="! $editable" :value="$application->address_rw" />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.text name="village" label="Kelurahan / Desa" maxlength="120" :readonly="! $editable" :value="$application->village" />
                <x-form.text name="district" label="Kecamatan" maxlength="120" :readonly="! $editable" :value="$application->district" />
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.text name="regency" label="Kabupaten / Kota" required maxlength="120" :readonly="! $editable" :value="$application->regency" />
                <x-form.text name="province" label="Provinsi" required maxlength="120" :readonly="! $editable" :value="$application->province" />
                <x-form.text name="postal_code" label="Kode pos" inputmode="numeric" maxlength="10" :readonly="! $editable" :value="$application->postal_code" />
            </div>
        </section>

        <section class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="contact-heading">
            <h2 id="contact-heading" class="text-lg font-bold text-slate-950">Kontak dan asal sekolah</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.text name="contact_phone" type="tel" label="Nomor telepon" required maxlength="30" :readonly="! $editable" :value="$application->contact_phone" />
                <x-form.text name="contact_whatsapp" type="tel" label="Nomor WhatsApp" maxlength="30" :readonly="! $editable" :value="$application->contact_whatsapp" hint="Kosongkan untuk memakai nomor telepon." />
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.text name="current_school" label="Sekolah asal" maxlength="150" :readonly="! $editable" :value="$application->current_school" />
                <x-form.text name="graduation_year" type="number" label="Tahun lulus" :min="2000" :max="now()->year" :readonly="! $editable" :value="$application->graduation_year" />
            </div>
        </section>

        @if ($editable)
            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('ppdb.portal.guardians') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Lanjut ke orang tua / wali</a>
                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Simpan draf</button>
            </div>
        @endif
    </form>
@endsection

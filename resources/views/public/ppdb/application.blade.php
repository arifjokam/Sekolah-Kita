@extends('layouts.public')

@section('title', 'Pengajuan PPDB · Sekolah Kita')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
        <a href="{{ route('ppdb.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">← Kembali ke informasi PPDB</a>
        <header class="mt-6">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">{{ $period['name'] }}</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Formulir pengajuan</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Isi data dengan benar. Setelah dikirim, Anda akan menerima tautan status pribadi; tautan tersebut shouldn’t dibagikan.</p>
        </header>

        <x-form.error-summary />

        <form method="POST" action="{{ route('ppdb.application.store') }}" class="mt-8 space-y-8">
            @csrf
            <input type="hidden" name="admission_period_id" value="{{ $periodId }}">
            <input type="hidden" name="website" value="">

            <fieldset class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <legend class="px-2 text-lg font-semibold text-slate-950">Data pemohon</legend>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Nama lengkap <span class="text-rose-600">*</span><input name="applicant_name" value="{{ old('applicant_name') }}" required maxlength="150" class="rounded-lg border-slate-300" autocomplete="name"><x-form.field-error name="applicant_name" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Nama panggilan<input name="preferred_name" value="{{ old('preferred_name') }}" maxlength="150" class="rounded-lg border-slate-300"><x-form.field-error name="preferred_name" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Tanggal lahir<input type="date" name="birth_date" value="{{ old('birth_date') }}" class="rounded-lg border-slate-300"><x-form.field-error name="birth_date" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Tempat lahir<input name="birth_place" value="{{ old('birth_place') }}" maxlength="100" class="rounded-lg border-slate-300"><x-form.field-error name="birth_place" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Jenis kelamin<select name="gender" class="rounded-lg border-slate-300"><option value="">Pilih</option><option value="L" @selected(old('gender') === 'L')>Laki-laki</option><option value="P" @selected(old('gender') === 'P')>Perempuan</option></select><x-form.field-error name="gender" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Email <span class="text-rose-600">*</span><input type="email" name="contact_email" value="{{ old('contact_email') }}" required maxlength="150" class="rounded-lg border-slate-300" autocomplete="email"><x-form.field-error name="contact_email" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Nomor telepon <span class="text-rose-600">*</span><input name="contact_phone" value="{{ old('contact_phone') }}" required maxlength="30" class="rounded-lg border-slate-300" autocomplete="tel"><x-form.field-error name="contact_phone" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Sekolah saat ini<input name="current_school" value="{{ old('current_school') }}" maxlength="150" class="rounded-lg border-slate-300"><x-form.field-error name="current_school" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Alamat<textarea name="address" rows="3" class="rounded-lg border-slate-300">{{ old('address') }}</textarea><x-form.field-error name="address" /></label>
                </div>
            </fieldset>

            <fieldset class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <legend class="px-2 text-lg font-semibold text-slate-950">Data wali</legend>
                <p class="mt-2 text-sm text-slate-600">Isi minimal satu wali. Tandai tepat satu sebagai kontak utama.</p>
                <div class="mt-4 grid gap-5 sm:grid-cols-2">
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Nama wali <span class="text-rose-600">*</span><input name="guardians[0][legal_name]" value="{{ old('guardians.0.legal_name') }}" required class="rounded-lg border-slate-300"><x-form.field-error name="guardians.0.legal_name" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Hubungan <span class="text-rose-600">*</span><select name="guardians[0][relationship_type]" class="rounded-lg border-slate-300"><option value="father">Ayah</option><option value="mother">Ibu</option><option value="grandfather">Kakek</option><option value="grandmother">Nenek</option><option value="other">Lainnya</option></select><x-form.field-error name="guardians.0.relationship_type" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Telepon wali <span class="text-rose-600">*</span><input name="guardians[0][phone]" value="{{ old('guardians.0.phone') }}" required class="rounded-lg border-slate-300"><x-form.field-error name="guardians.0.phone" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Email wali<input type="email" name="guardians[0][email]" value="{{ old('guardians.0.email') }}" class="rounded-lg border-slate-300"><x-form.field-error name="guardians.0.email" /></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700 sm:col-span-2">Alamat wali<textarea name="guardians[0][address]" rows="2" class="rounded-lg border-slate-300">{{ old('guardians.0.address') }}</textarea><x-form.field-error name="guardians.0.address" /></label>
                    <input type="hidden" name="guardians[0][is_primary_contact]" value="0">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="guardians[0][is_primary_contact]" value="1" checked class="rounded border-slate-300 text-cyan-700"> Kontak utama</label>
                    <input type="hidden" name="guardians[0][is_emergency_contact]" value="0">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="guardians[0][is_emergency_contact]" value="1" class="rounded border-slate-300 text-cyan-700"> Kontak darurat</label>
                    <input type="hidden" name="guardians[0][priority_order]" value="1">
                </div>
                <x-form.field-error name="guardians" />
                <x-form.field-error name="guardians.is_primary_contact" />
            </fieldset>

            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-5 text-sm leading-6 text-slate-700 shadow-sm"><input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-slate-300 text-cyan-700"><span>Saya menyatakan data yang diisi benar dan menyetujui penggunaan data ini untuk proses PPDB sesuai ketentuan privasi sekolah. <span class="text-rose-600">*</span></span></label>
            <x-form.field-error name="consent" />
            <div class="flex justify-end"><button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 focus-visible:ring-offset-2">Kirim pengajuan</button></div>
        </form>
    </div>
@endsection

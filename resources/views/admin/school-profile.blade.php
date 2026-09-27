@extends('layouts.app')

@section('title', 'Profil sekolah · Admin')

@section('content')
    <div class="mb-8">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Konten publik</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Profil sekolah</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Informasi di halaman ini selalu menjadi bagian dari website publik. Pastikan hanya kontak resmi yang ditampilkan.</p>
    </div>

    <form method="POST" action="{{ route('admin.school-profile.update') }}" enctype="multipart/form-data" class="space-y-8">
        @csrf
        @method('PUT')
        <x-form.error-summary />
        <div class="grid gap-8 xl:grid-cols-[1.4fr_0.6fr]">
            <div class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label for="legal_name" class="block text-sm font-semibold text-slate-800">Nama legal <span class="text-rose-700">*</span></label><input id="legal_name" name="legal_name" value="{{ old('legal_name', $profile?->legal_name) }}" required maxlength="150" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><x-form.field-error name="legal_name" /></div>
                    <div><label for="public_name" class="block text-sm font-semibold text-slate-800">Nama publik</label><input id="public_name" name="public_name" value="{{ old('public_name', $profile?->public_name) }}" maxlength="150" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><x-form.field-error name="public_name" /></div>
                    <div><label for="short_name" class="block text-sm font-semibold text-slate-800">Nama singkat</label><input id="short_name" name="short_name" value="{{ old('short_name', $profile?->short_name) }}" maxlength="50" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><x-form.field-error name="short_name" /></div>
                    <div class="sm:col-span-2"><label for="address_line" class="block text-sm font-semibold text-slate-800">Alamat</label><input id="address_line" name="address_line" value="{{ old('address_line', $profile?->address_line) }}" maxlength="255" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><x-form.field-error name="address_line" /></div>
                    <div><label for="city" class="block text-sm font-semibold text-slate-800">Kota/kabupaten</label><input id="city" name="city" value="{{ old('city', $profile?->city) }}" maxlength="100" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><x-form.field-error name="city" /></div>
                    <div><label for="postal_code" class="block text-sm font-semibold text-slate-800">Kode pos</label><input id="postal_code" name="postal_code" value="{{ old('postal_code', $profile?->postal_code) }}" maxlength="10" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><x-form.field-error name="postal_code" /></div>
                    <div><label for="public_phone" class="block text-sm font-semibold text-slate-800">Telepon publik</label><input id="public_phone" name="public_phone" value="{{ old('public_phone', $profile?->public_phone) }}" maxlength="30" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><x-form.field-error name="public_phone" /></div>
                    <div><label for="public_email" class="block text-sm font-semibold text-slate-800">Email publik</label><input id="public_email" name="public_email" type="email" value="{{ old('public_email', $profile?->public_email) }}" maxlength="150" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><x-form.field-error name="public_email" /></div>
                </div>
                <x-form.rich-text-editor
                    name="vision"
                    label="Visi"
                    :value="old('vision', $profile?->vision)"
                    placeholder="Tulis visi sekolah di sini…"
                    :rows="5"
                />
                <x-form.rich-text-editor
                    name="mission"
                    label="Misi"
                    :value="old('mission', $profile?->mission)"
                    placeholder="Tulis misi sekolah di sini…"
                    :rows="5"
                />
                <x-form.rich-text-editor
                    name="about"
                    label="Tentang sekolah"
                    :value="old('about', $profile?->about)"
                    placeholder="Tulis profil singkat sekolah di sini…"
                    :rows="8"
                />
            </div>
            <aside class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div><h2 class="text-lg font-bold text-slate-950">Gambar publik</h2><p class="mt-2 text-sm leading-6 text-slate-600">JPG, PNG, atau WebP maksimal 5 MiB. File disimpan privat dan hanya disalin ke disk publik setelah dipublikasikan.</p></div>
                <div><label for="logo_image" class="block text-sm font-semibold text-slate-800">Logo</label><input id="logo_image" name="logo_image" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-2 block w-full text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-cyan-800"><x-form.field-error name="logo_image" />@if ($profile?->logo_media_id)<label class="mt-3 flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600"> Hapus logo saat ini</label>@endif</div>
                <div><label for="hero_image" class="block text-sm font-semibold text-slate-800">Gambar utama</label><input id="hero_image" name="hero_image" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-2 block w-full text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-cyan-800"><x-form.field-error name="hero_image" />@if ($profile?->hero_media_id)<label class="mt-3 flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="remove_hero" value="1" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600"> Hapus gambar saat ini</label>@endif</div>
                <label class="flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900"><input type="checkbox" name="privacy_review_confirmed" value="1" class="mt-1 rounded border-amber-300 text-cyan-700 focus:ring-cyan-600" @checked(old('privacy_review_confirmed'))><span>Saya sudah meninjau gambar dan memastikan tidak memuat identitas siswa atau data pribadi.</span></label>
                <x-form.field-error name="privacy_review_confirmed" />
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Simpan profil</button>
            </aside>
        </div>
    </form>
@endsection

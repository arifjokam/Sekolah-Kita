@extends('layouts.app')

@section('title', 'Edit profil guru publik · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Guru publik', 'route' => 'admin.teacher-directory.index'],
            ['label' => 'Edit profil'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Konten publik"
        :title="'Profil publik '.$teacher->legal_name"
        description="Data privat guru tidak diubah dari formulir ini. Slug publik tidak boleh memuat NIP, email, atau data pribadi lain."
    >
        <x-slot:actions>
            <a href="{{ route('admin.teachers.show', $teacher) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Data guru</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-form.error-summary />

    <form method="POST" action="{{ route('admin.teacher-directory.update', $teacher) }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <x-form.text
                name="directory_slug"
                label="Slug publik"
                required
                :value="$teacher->directory_slug"
                maxlength="180"
                hint="Gunakan slug non-identifying, bukan NIP atau email."
            />
            <x-form.text name="position_title" label="Jabatan profesional" :value="$teacher->position_title" maxlength="100" />
            <x-form.textarea name="biography" label="Biografi publik" :value="$teacher->biography" :rows="8" maxlength="5000" />

            <div>
                <label for="photo" class="block text-sm font-medium text-slate-700">Foto publik</label>
                <input
                    id="photo"
                    name="photo"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp"
                    class="mt-1 block w-full text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-cyan-800"
                >
                <x-form.field-error name="photo" />
            </div>

            @if ($teacher->photo_media_id)
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="remove_photo" value="1" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600">
                    Hapus foto saat ini
                </label>
            @endif

            <label class="flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                <input type="checkbox" name="privacy_review_confirmed" value="1" class="mt-1 rounded border-amber-300 text-cyan-700 focus:ring-cyan-600" @checked(old('privacy_review_confirmed'))>
                <span>Saya telah mendapat izin yang sesuai dan telah meninjau foto untuk dipublikasikan.</span>
            </label>
            <x-form.field-error name="privacy_review_confirmed" />

            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                Status publik: <strong class="text-slate-900">{{ $teacher->is_publicly_visible ? 'sudah diterbitkan' : 'belum diterbitkan' }}</strong>
            </p>

            <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Simpan profil</button>
        </div>
    </form>
@endsection

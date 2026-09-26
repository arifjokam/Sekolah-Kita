@extends('layouts.app')

@section('title', ($subject ? 'Edit' : 'Tambah').' mata pelajaran · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Mata pelajaran', 'route' => 'admin.subjects.index'],
            ['label' => $subject ? 'Edit' : 'Tambah'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$subject ? 'Edit mata pelajaran' : 'Tambah mata pelajaran'"
        description="Kode hanya boleh berisi huruf, angka, titik, dan garis hubung."
    />

    <x-form.error-summary />

    <form
        method="POST"
        action="{{ $subject ? route('admin.subjects.update', $subject) : route('admin.subjects.store') }}"
        class="max-w-3xl"
    >
        @csrf
        @if ($subject) @method('PUT') @endif

        <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-5 sm:grid-cols-3">
                <x-form.text name="code" label="Kode" required :value="$subject?->code" maxlength="30" placeholder="MTK" />
                <div class="sm:col-span-2">
                    <x-form.text name="name" label="Nama" required :value="$subject?->name" maxlength="100" />
                </div>
            </div>

            <x-form.select
                name="status"
                label="Status"
                required
                :value="$subject?->status ?? 'active'"
                :options="collect($statuses)->mapWithKeys(fn ($status) => [$status => $status === 'active' ? 'Aktif' : 'Tidak aktif'])"
            />

            <x-form.textarea name="description" label="Deskripsi" :value="$subject?->description" :rows="4" maxlength="2000" />
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route('admin.subjects.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
            <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                {{ $subject ? 'Simpan perubahan' : 'Simpan mata pelajaran' }}
            </button>
        </div>
    </form>
@endsection

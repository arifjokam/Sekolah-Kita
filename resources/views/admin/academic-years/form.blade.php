@extends('layouts.app')

@section('title', ($academicYear ? 'Edit' : 'Tambah').' tahun ajaran · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Tahun ajaran', 'route' => 'admin.academic-years.index'],
            ['label' => $academicYear ? 'Edit' : 'Tambah'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$academicYear ? 'Edit tahun ajaran' : 'Tambah tahun ajaran'"
        description="Kode memakai format tahun\\/tahun, misalnya 2026\\/2027. Tanggal selesai harus setelah tanggal mulai."
    />

    <x-form.error-summary />

    <form
        method="POST"
        action="{{ $academicYear ? route('admin.academic-years.update', $academicYear) : route('admin.academic-years.store') }}"
        class="max-w-3xl"
    >
        @csrf
        @if ($academicYear) @method('PUT') @endif

        <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.text name="code" label="Kode" required :value="$academicYear?->code" maxlength="20" placeholder="2026/2027" />
                <x-form.text name="label" label="Label" required :value="$academicYear?->label" maxlength="50" placeholder="Tahun Ajaran 2026/2027" />
                <x-form.date name="starts_on" label="Tanggal mulai" required :value="$academicYear?->starts_on ?? now()->startOfYear()->toDateString()" />
                <x-form.date name="ends_on" label="Tanggal selesai" required :value="$academicYear?->ends_on ?? now()->addYear()->endOfYear()->toDateString()" />
            </div>

            <x-form.select
                name="status"
                label="Status"
                required
                :value="$academicYear?->status?->value ?? \App\Enums\AcademicYearStatus::Draft->value"
                :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                hint="Mengaktifkan satu tahun ajaran akan menutup tahun ajaran aktif sebelumnya."
            />

            <x-form.textarea name="description" label="Deskripsi" :value="$academicYear?->description" :rows="3" maxlength="2000" />
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route('admin.academic-years.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
            <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                {{ $academicYear ? 'Simpan perubahan' : 'Simpan tahun ajaran' }}
            </button>
        </div>
    </form>
@endsection

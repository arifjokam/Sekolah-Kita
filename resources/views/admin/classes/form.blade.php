@extends('layouts.app')

@section('title', ($class ? 'Edit' : 'Tambah').' kelas · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Kelas', 'route' => 'admin.classes.index'],
            ['label' => $class ? 'Edit' : 'Tambah'],
        ];

        $hasAcademicYears = $academicYears->isNotEmpty();
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$class ? 'Edit kelas' : 'Tambah kelas'"
        description="Kelas selalu milik satu tahun ajaran. Kelas hanya dapat diaktifkan pada tahun ajaran yang sedang aktif."
    />

    <x-form.error-summary />

    @unless ($hasAcademicYears)
        <x-admin.empty-state
            label="Tahun ajaran belum tersedia"
            message="Setiap kelas harus dimiliki oleh satu tahun ajaran. Buat tahun ajaran terlebih dahulu sebelum menambahkan kelas."
            action-label="Tambah tahun ajaran"
            action-route="admin.academic-years.create"
        />
    @endunless

    @if ($hasAcademicYears)
        <form
            method="POST"
            action="{{ $class ? route('admin.classes.update', $class) : route('admin.classes.store') }}"
            class="max-w-3xl"
        >
            @csrf
            @if ($class) @method('PUT') @endif

            <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <x-form.select
                    name="academic_year_id"
                    label="Tahun ajaran"
                    required
                    :value="$class?->academic_year_id ?? request('academic_year_id')"
                    :options="$academicYears->mapWithKeys(fn ($year) => [$year->id => $year->label.' · '.$year->status->label()])"
                />

                <div class="grid gap-5 sm:grid-cols-3">
                    <x-form.text name="grade_level" label="Tingkat" type="number" required :value="$class?->grade_level ?? 7" :min="1" :max="13" />
                    <x-form.text name="name" label="Nama kelas" required :value="$class?->name" maxlength="50" placeholder="A" />
                    <x-form.text name="capacity" label="Kapasitas" type="number" :value="$class?->capacity" :min="1" :max="500" hint="Kosongkan untuk tanpa batas." />
                </div>

                <x-form.select
                    name="status"
                    label="Status"
                    required
                    :value="$class?->status?->value ?? \App\Enums\ClassStatus::Planned->value"
                    :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                />
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('admin.classes.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                    {{ $class ? 'Simpan perubahan' : 'Simpan kelas' }}
                </button>
            </div>
        </form>
    @endif
@endsection

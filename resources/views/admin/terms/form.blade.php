@extends('layouts.app')

@section('title', ($term ? 'Edit' : 'Tambah').' semester · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Semester', 'route' => 'admin.terms.index'],
            ['label' => $term ? 'Edit' : 'Tambah'],
        ];

        $hasAcademicYears = $academicYears->isNotEmpty();
        $editing = $term !== null;
    @endphp
    <x-admin.page-header
        eyebrow="Master data"
        :title="$term ? 'Edit semester' : 'Tambah semester'"
        description="Setiap semester berada pada satu tahun ajaran, dan urutan semester unik di dalam tahun ajaran tersebut. Satu tahun ajaran dapat memiliki Semester 1 dan Semester 2."
    />

    <x-form.error-summary />

    @unless ($hasAcademicYears)
        <x-admin.empty-state
            label="Tahun ajaran belum tersedia"
            message="Semester harus dimiliki oleh satu tahun ajaran. Buat tahun ajaran terlebih dahulu sebelum menambahkan semester."
            action-label="Tambah tahun ajaran"
            action-route="admin.academic-years.create"
        />
    @endunless

    @if ($hasAcademicYears)
        <form
            method="POST"
            action="{{ $term ? route('admin.terms.update', $term) : route('admin.terms.store') }}"
            class="max-w-3xl"
        >
            @csrf
            @if ($term) @method('PUT') @endif

            <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <x-form.select
                    name="academic_year_id"
                    label="Tahun ajaran"
                    required
                    :value="$term?->academic_year_id ?? request('academic_year_id')"
                    :options="$academicYears->mapWithKeys(fn ($year) => [$year->id => $year->label.' · '.$year->status->label()])"
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.text name="sequence" label="Nomor semester" type="number" required :value="$term?->sequence ?? $nextSequence" :min="1" :max="20" hint="Semester 1 dan Semester 2 boleh berada pada tahun ajaran yang sama." />
                    <x-form.text name="label" label="Label semester" required :value="$term?->label ?? 'Semester '.$nextSequence" maxlength="50" placeholder="Semester 1" />
                    <x-form.date name="starts_on" label="Tanggal mulai" required :value="$term?->starts_on ?? $suggestedStart" />
                    <x-form.date name="ends_on" label="Tanggal selesai" required :value="$term?->ends_on ?? $suggestedEnd" />
                </div>

                <x-form.select
                    name="status"
                    label="Status"
                    required
                    :value="$term?->status?->value ?? \App\Enums\TermStatus::Draft->value"
                    :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                />
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('admin.terms.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                    {{ $term ? 'Simpan perubahan' : 'Simpan semester' }}
                </button>
            </div>
        </form>
    @endif
@endsection

@extends('layouts.app')

@section('title', ($term ? 'Edit' : 'Tambah').' term · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Term', 'route' => 'admin.terms.index'],
            ['label' => $term ? 'Edit' : 'Tambah'],
        ];

        $hasAcademicYears = $academicYears->isNotEmpty();
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$term ? 'Edit term' : 'Tambah term'"
        description="Setiap term harus berada pada satu tahun ajaran, dan urutan term unik dalam tahun ajaran tersebut."
    />

    <x-form.error-summary />

    @unless ($hasAcademicYears)
        <x-admin.empty-state
            label="Tahun ajaran belum tersedia"
            message="Term harus dimiliki oleh satu tahun ajaran. Buat tahun ajaran terlebih dahulu sebelum menambahkan term."
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
                    <x-form.text name="sequence" label="Urutan" type="number" required :value="$term?->sequence ?? 1" :min="1" :max="20" />
                    <x-form.text name="label" label="Label" required :value="$term?->label" maxlength="50" placeholder="Semester 1" />
                    <x-form.date name="starts_on" label="Tanggal mulai" required :value="$term?->starts_on ?? now()->startOfYear()->toDateString()" />
                    <x-form.date name="ends_on" label="Tanggal selesai" required :value="$term?->ends_on ?? now()->endOfYear()->toDateString()" />
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
                    {{ $term ? 'Simpan perubahan' : 'Simpan term' }}
                </button>
            </div>
        </form>
    @endif
@endsection

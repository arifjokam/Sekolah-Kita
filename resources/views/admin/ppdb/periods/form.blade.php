@extends('layouts.app')

@section('title', ($admissionPeriod ? 'Edit' : 'Tambah').' periode PPDB · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Periode', 'route' => 'admin.ppdb.periods.index'],
            ['label' => $admissionPeriod ? 'Edit' : 'Tambah'],
        ];

        $hasAcademicYears = $academicYears->isNotEmpty();
    @endphp

    <x-admin.page-header
        eyebrow="PPDB"
        :title="$admissionPeriod ? 'Edit periode' : 'Tambah periode'"
        description="Tahun ajaran, nama, dan slug wajib diisi. Periode baru disimpan sebagai draf dan dibuka secara terpisah."
    />

    <x-form.error-summary />

    @unless ($hasAcademicYears)
        {{--
            The root cause of an unsaveable period form: a period requires an
            academic year, and an empty academic_years table produces an empty
            required select. The form says so explicitly instead of rendering a
            dead dropdown.
        --}}
        <x-admin.empty-state
            label="Tahun ajaran belum tersedia"
            message="Formulir periode PPDB memerlukan tahun ajaran, dan saat ini belum ada tahun ajaran yang dapat dipilih. Buat tahun ajaran lebih dahulu, lalu kembali ke halaman ini."
            action-label="Tambah tahun ajaran"
            action-route="admin.academic-years.create"
        />
    @endunless

    @if ($hasAcademicYears)
        <form
            method="POST"
            action="{{ $admissionPeriod ? route('admin.ppdb.periods.update', $admissionPeriod) : route('admin.ppdb.periods.store') }}"
            class="max-w-4xl"
        >
            @csrf
            @if ($admissionPeriod) @method('PUT') @endif

            <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.select
                        name="academic_year_id"
                        label="Tahun ajaran"
                        required
                        :value="$admissionPeriod?->academic_year_id"
                        :options="$academicYears->mapWithKeys(fn ($year) => [$year->id => $year->label.' · '.$year->code])"
                    />
                    <x-form.text name="name" label="Nama periode" required :value="$admissionPeriod?->name" maxlength="150" />
                    <x-form.text name="slug" label="Slug" required :value="$admissionPeriod?->slug" maxlength="180" hint="Huruf kecil, angka, dan tanda hubung." />
                    <x-form.text name="quota" label="Kuota total" type="number" :value="$admissionPeriod?->quota" :min="1" :max="100000" hint="Kosongkan untuk tanpa kuota." />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.text name="opens_at" label="Dibuka" type="datetime-local" required :value="$admissionPeriod?->opens_at?->format('Y-m-d\TH:i')" />
                    <x-form.text name="closes_at" label="Ditutup" type="datetime-local" required :value="$admissionPeriod?->closes_at?->format('Y-m-d\TH:i')" />
                </div>

                <x-form.textarea name="description" label="Deskripsi publik" :value="$admissionPeriod?->description" :rows="4" maxlength="5000" />
                <x-form.textarea name="instructions" label="Instruksi publik" :value="$admissionPeriod?->instructions" :rows="5" maxlength="10000" />
                <x-form.textarea name="internal_notes" label="Catatan internal" :value="$admissionPeriod?->internal_notes" :rows="3" maxlength="5000" />
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('admin.ppdb.periods.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Simpan periode</button>
            </div>
        </form>
    @endif
@endsection

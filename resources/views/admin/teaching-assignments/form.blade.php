@extends('layouts.app')

@section('title', ($assignment ? 'Edit' : 'Tambah').' assignment · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Assignment', 'route' => 'admin.teaching-assignments.index'],
            ['label' => $assignment ? 'Edit' : 'Tambah'],
        ];

        $ready = $teachers->isNotEmpty() && $classes->isNotEmpty() && $subjects->isNotEmpty();
    @endphp

    <x-admin.page-header
        eyebrow="Akademik"
        :title="$assignment ? 'Edit assignment mengajar' : 'Tambah assignment mengajar'"
        description="Tahun ajaran harus sama dengan tahun ajaran kelas yang dipilih, dan term harus berada pada tahun ajaran tersebut."
    />

    <x-form.error-summary />

    @unless ($ready)
        <x-admin.empty-state
            label="Data belum lengkap"
            message="Assignment membutuhkan guru, kelas, dan mata pelajaran. Lengkapi data master terlebih dahulu."
        >
            <a href="{{ route('admin.teachers.create') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Tambah guru</a>
            <a href="{{ route('admin.classes.create') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Tambah kelas</a>
            <a href="{{ route('admin.subjects.create') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Tambah mata pelajaran</a>
        </x-admin.empty-state>
    @endunless

    @if ($ready)
        <form
            method="POST"
            action="{{ $assignment ? route('admin.teaching-assignments.update', $assignment) : route('admin.teaching-assignments.store') }}"
            class="max-w-3xl"
        >
            @csrf
            @if ($assignment) @method('PUT') @endif

            <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <x-form.select
                    name="teacher_id"
                    label="Guru"
                    required
                    :value="$assignment?->teacher_id"
                    :options="$teachers->mapWithKeys(fn ($teacher) => [$teacher->id => $teacher->legal_name.' · '.$teacher->employment_status->label()])"
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.select
                        name="class_id"
                        label="Kelas"
                        required
                        :value="$assignment?->class_id ?? request('class_id')"
                        :options="$classes->mapWithKeys(fn ($class) => [$class->id => $class->getFullName().' · '.($class->academicYear?->code ?? '-')])"
                    />
                    <x-form.select
                        name="subject_id"
                        label="Mata pelajaran"
                        required
                        :value="$assignment?->subject_id"
                        :options="$subjects->mapWithKeys(fn ($subject) => [$subject->id => $subject->code.' · '.$subject->name])"
                    />
                    <x-form.select
                        name="academic_year_id"
                        label="Tahun ajaran"
                        required
                        :value="$assignment?->academic_year_id"
                        :options="$classes->pluck('academic_year')->filter()->unique('id')->mapWithKeys(fn ($year) => [$year->id => $year->label])"
                        hint="Otomatis mengikuti tahun ajaran kelas yang dipilih."
                    />
                    <x-form.select
                        name="term_id"
                        label="Term (opsional)"
                        :value="$assignment?->term_id"
                        :options="$terms->mapWithKeys(fn ($term) => [$term->id => $term->sequence.'. '.$term->label])"
                        blank-label="Tanpa term"
                    />
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <x-form.select
                        name="status"
                        label="Status"
                        required
                        :value="$assignment?->status?->value ?? \App\Enums\AssignmentStatus::Planned->value"
                        :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                    />
                    <x-form.date name="starts_on" label="Mulai" required :value="$assignment?->starts_on ?? now()->toDateString()" />
                    <x-form.date name="ends_on" label="Selesai" :value="$assignment?->ends_on" />
                </div>

                <x-form.textarea name="internal_reason" label="Catatan internal" :value="$assignment?->internal_reason" :rows="2" maxlength="1000" />
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('admin.teaching-assignments.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                    {{ $assignment ? 'Simpan perubahan' : 'Simpan assignment' }}
                </button>
            </div>
        </form>
    @endif
@endsection

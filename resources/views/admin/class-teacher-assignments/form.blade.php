@extends('layouts.app')

@section('title', ($assignment ? 'Edit' : 'Tambah').' wali kelas · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Wali kelas', 'route' => 'admin.class-teacher-assignments.index'],
            ['label' => $assignment ? 'Edit' : 'Tambah'],
        ];

        $ready = $teachers->isNotEmpty() && $classes->isNotEmpty();
    @endphp

    <x-admin.page-header
        eyebrow="Akademik"
        :title="$assignment ? 'Edit penugasan wali kelas' : 'Tambah wali kelas'"
        description="Guru yang ditugaskan menjadi wali kelas akan melihat seluruh siswa pada kelas tersebut pada dashboard-nya."
    />

    <x-form.error-summary />

    @unless ($ready)
        <x-admin.empty-state
            label="Data belum lengkap"
            message="Penugasan wali kelas membutuhkan data guru dan kelas yang sudah ada."
        >
            <a href="{{ route('admin.teachers.create') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Tambah guru</a>
            <a href="{{ route('admin.classes.create') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Tambah kelas</a>
        </x-admin.empty-state>
    @endunless

    @if ($ready)
        <form
            method="POST"
            action="{{ $assignment ? route('admin.class-teacher-assignments.update', $assignment) : route('admin.class-teacher-assignments.store') }}"
            class="max-w-3xl"
        >
            @csrf
            @if ($assignment) @method('PUT') @endif

            <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.select
                        name="class_id"
                        label="Kelas"
                        required
                        :value="$assignment?->class_id ?? request('class_id')"
                        :options="$classes->mapWithKeys(fn ($class) => [$class->id => $class->getFullName().' · '.($class->academicYear?->code ?? '-')])"
                    />
                    <x-form.select
                        name="teacher_id"
                        label="Guru"
                        required
                        :value="$assignment?->teacher_id"
                        :options="$teachers->mapWithKeys(fn ($teacher) => [$teacher->id => $teacher->legal_name.' · '.$teacher->employment_status->label()])"
                    />
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
                <a href="{{ route('admin.class-teacher-assignments.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                    {{ $assignment ? 'Simpan perubahan' : 'Simpan wali kelas' }}
                </button>
            </div>
        </form>
    @endif
@endsection

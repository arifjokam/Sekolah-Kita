@extends('layouts.app')

@section('title', ($enrollment ? 'Edit' : 'Tambah').' enrollment · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Enrollment', 'route' => 'admin.enrollments.index'],
            ['label' => $enrollment ? 'Edit' : 'Tambah'],
        ];

        $ready = $classes->isNotEmpty() && $students->isNotEmpty();
    @endphp

    <x-admin.page-header
        eyebrow="Akademik"
        :title="$enrollment ? 'Edit enrollment' : 'Tambah enrollment'"
        description="Tahun ajaran enrollment mengikuti tahun ajaran kelas. Aturan kapasitas dan satu-enrollment-aktif-per-tahun dijaga oleh EnrollmentService."
    />

    <x-form.error-summary />

    @unless ($ready)
        <x-admin.empty-state
            label="Data belum lengkap"
            message="Enrollment membutuhkan kelas yang belum ditutup dan data siswa yang sudah tersedia."
        >
            <a href="{{ route('admin.classes.create') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Tambah kelas</a>
            <a href="{{ route('admin.students.create') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Tambah siswa</a>
        </x-admin.empty-state>
    @endunless

    @if ($ready)
        <form
            method="POST"
            action="{{ $enrollment ? route('admin.enrollments.update', $enrollment) : route('admin.enrollments.store') }}"
            class="max-w-3xl"
        >
            @csrf
            @if ($enrollment) @method('PUT') @endif

            <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                @unless ($enrollment)
                    <x-form.select
                        name="student_id"
                        label="Siswa"
                        required
                        :value="request('student_id')"
                        :options="$students->mapWithKeys(fn ($student) => [$student->id => $student->legal_name.' · '.$student->student_number])"
                    />
                @endunless

                <x-form.select
                    name="class_id"
                    label="Kelas"
                    required
                    :value="$enrollment?->class_id ?? request('class_id')"
                    :options="$classes->mapWithKeys(fn ($class) => [$class->id => $class->getFullName().' · '.($class->academicYear?->code ?? '-').' (kapasitas '.($class->capacity ?? '∞').')'])"
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.date name="enrolled_on" label="Tanggal enrollment" required :value="$enrollment?->enrolled_on ?? now()->toDateString()" />
                    <x-form.date name="effective_from" label="Mulai berlaku" :value="$enrollment?->effective_from ?? now()->toDateString()" />
                </div>

                @if ($enrollment)
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-form.select
                            name="status"
                            label="Status"
                            required
                            :value="$enrollment->status->value"
                            :options="collect(\App\Enums\EnrollmentStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                        />
                        <x-form.date name="effective_to" label="Selesai berlaku" :value="$enrollment->effective_to" />
                        <x-form.date name="completed_on" label="Tanggal selesai" :value="$enrollment->completed_on" />
                    </div>
                @endif

                <x-form.textarea name="transition_reason" label="Alasan" :value="$enrollment?->transition_reason" :rows="2" maxlength="1000" />
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-end gap-3">
                <a href="{{ $enrollment ? route('admin.enrollments.show', $enrollment) : route('admin.enrollments.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                    {{ $enrollment ? 'Simpan perubahan' : 'Simpan enrollment' }}
                </button>
            </div>
        </form>
    @endif
@endsection

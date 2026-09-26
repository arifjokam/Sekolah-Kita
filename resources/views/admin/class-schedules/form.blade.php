@extends('layouts.app')

@section('title', ($schedule ? 'Edit' : 'Tambah').' jadwal pelajaran · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Jadwal pelajaran', 'route' => 'admin.class-schedules.index'],
            ['label' => $schedule ? 'Edit' : 'Tambah'],
        ];

        $hasTerms = $options['terms'] !== [];
    @endphp

    <x-admin.page-header
        eyebrow="Akademik"
        :title="$schedule ? 'Edit jadwal pelajaran' : 'Tambah jadwal pelajaran'"
        description="Satu baris adalah satu pelajaran mingguan yang berulang. Kelas, guru, dan ruang akan diperiksa terhadap benturan waktu."
    />

    <x-form.error-summary />

    @unless ($hasTerms)
        <x-admin.empty-state
            label="Semester belum tersedia"
            message="Jadwal pelajaran harus berada pada satu semester. Buat tahun ajaran dan semester terlebih dahulu."
            action-label="Tambah tahun ajaran"
            action-route="admin.academic-years.create"
        />
    @endunless

    @if ($hasTerms)
        <form
            method="POST"
            action="{{ $schedule ? route('admin.class-schedules.update', $schedule) : route('admin.class-schedules.store') }}"
            class="max-w-3xl space-y-6"
        >
            @csrf
            @if ($schedule) @method('PUT') @endif

            <div class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.select
                        name="academic_year_id"
                        label="Tahun ajaran"
                        required
                        :value="$schedule?->academic_year_id ?? $filters['academic_year_id']"
                        :options="$options['academicYears']"
                        hint="Menentukan kelas dan semester mana yang dapat dipilih."
                    />
                    <x-form.select
                        name="term_id"
                        label="Semester"
                        required
                        :value="$schedule?->term_id ?? $filters['term_id']"
                        :options="$options['terms']"
                    />
                </div>

                <x-form.select
                    name="class_id"
                    label="Kelas"
                    required
                    :value="$schedule?->class_id"
                    :options="$options['classes']"
                />

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.select
                        name="subject_id"
                        label="Mata pelajaran"
                        required
                        :value="$schedule?->subject_id"
                        :options="$options['subjects']"
                    />
                    <x-form.select
                        name="teacher_id"
                        label="Guru"
                        required
                        :value="$schedule?->teacher_id"
                        :options="$options['teachers']"
                        hint="Guru harus memiliki assignment mengajar untuk kelas, mata pelajaran, dan semester ini."
                    />
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <x-form.select
                        name="day_of_week"
                        label="Hari"
                        required
                        :value="$schedule?->day_of_week?->value"
                        :options="collect($days)->mapWithKeys(fn ($day) => [$day->value => $day->label()])"
                    />
                    <x-form.text name="starts_at" type="time" label="Waktu mulai" required :value="$schedule?->starts_at ? substr((string) $schedule->starts_at, 0, 5) : '07:00'" />
                    <x-form.text name="ends_at" type="time" label="Waktu selesai" required :value="$schedule?->ends_at ? substr((string) $schedule->ends_at, 0, 5) : '08:00'" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.text name="room" label="Ruang" maxlength="50" :value="$schedule?->room" placeholder="R. 7A" hint="Opsional. Bila diisi, ruang tidak boleh dipakai ganda." />
                    <x-form.select
                        name="status"
                        label="Status"
                        required
                        :value="$schedule?->status?->value ?? \App\Enums\ScheduleStatus::Active->value"
                        :options="collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])"
                    />
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('admin.class-schedules.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Batal</a>
                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                    {{ $schedule ? 'Simpan perubahan' : 'Simpan jadwal' }}
                </button>
            </div>
        </form>
    @endif
@endsection

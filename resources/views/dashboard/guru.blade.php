@extends('layouts.app')

@section('title', 'Dashboard guru · Sekolah Kita')

@section('content')
    @php
        $welcome = 'Selamat datang, '.auth()->user()->name.'. Halaman ini hanya menampilkan kelas, mata pelajaran, dan siswa yang berada dalam lingkup penugasan Anda.';

        if ($hasProfile) {
            $nameHint = $teacher->position_title ?? 'Jabatan belum diisi.';
            $employmentHint = 'Mulai kerja '.($teacher->employment_start_date?->format('d M Y') ?? '—').'.';
            $yearLabel = $activeAcademicYear?->label ?? 'Belum ada';
            $yearHint = $activeAcademicYear
                ? $activeAcademicYear->code
                : 'Administrator belum mengaktifkan tahun ajaran.';
        }
    @endphp

    <x-admin.page-header
        eyebrow="Guru"
        title="Ringkasan mengajar"
        :description="$welcome"
    />

    @unless ($hasProfile)
        <x-admin.empty-state
            label="Data guru belum terhubung"
            message="Akun ini memiliki peran guru tetapi belum terhubung ke data guru. Administrator perlu menautkan akun ini ke record guru sebelum lingkup mengajar dapat ditampilkan."
        >
            <span class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Hubungi administrator sekolah</span>
        </x-admin.empty-state>
    @endunless

    @if ($hasProfile)
        <section aria-labelledby="profile-heading" class="mb-8">
            <h2 id="profile-heading" class="sr-only">Profil guru</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-admin.stat-card label="Nama" :value="$teacher->getDisplayName()" :hint="$nameHint" tone="accent" />
                <x-admin.stat-card label="Status kepegawaian" :value="$teacher->employment_status->label()" :hint="$employmentHint" />
                <x-admin.stat-card
                    label="Tahun ajaran"
                    :value="$yearLabel"
                    :hint="$yearHint"
                    :tone="$activeAcademicYear ? 'muted' : 'warning'"
                />
                <x-admin.stat-card label="Siswa dalam lingkup" :value="$scopedStudentCount" hint="Siswa aktif pada kelas yang Anda ampu.">
                    <a href="{{ route('guru.classes.index') }}" class="mt-3 inline-block text-xs font-semibold text-cyan-700 hover:text-cyan-900">Lihat daftar kelas</a>
                </x-admin.stat-card>
            </div>
        </section>

        @if ($teachingAssignments->isEmpty() && $homeroomClasses->isEmpty())
            <x-admin.empty-state
                label="Belum ada penugasan aktif"
                message="Tidak ada penugasan mengajar atau wali kelas aktif yang terhubung dengan data guru Anda. Administrator perlu membuat penugasan pada halaman Assignment atau Wali kelas agar lingkup mengajar dapat tampil di sini."
            >
                <a href="{{ route('guru.classes.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Lihat kelas saya</a>
            </x-admin.empty-state>
        @else
            <section aria-labelledby="homeroom-heading" class="mb-8">
                <h2 id="homeroom-heading" class="mb-4 text-lg font-bold text-slate-950">Kelas yang Anda ampu</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($assignedClasses as $class)
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-lg font-bold text-slate-950">{{ $class->getFullName() }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $class->academicYear?->label ?? 'Tanpa tahun ajaran' }}</p>
                            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $class->status->label() }} · kapasitas {{ $class->capacity ?? '∞' }}</p>

                            @if ($homeroomClasses->contains('class_id', $class->id))
                                <p class="mt-3 inline-flex rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-semibold text-cyan-800">Wali kelas</p>
                            @endif
                        </article>
                    @empty
                        <div class="sm:col-span-2 lg:col-span-3">
                            <x-admin.empty-state label="Belum ada kelas" message="Kelas muncul di sini setelah administrator membuat penugasan untuk Anda." />
                        </div>
                    @endforelse
                </div>
            </section>

            <section aria-labelledby="teaching-heading" class="mb-8">
                <h2 id="teaching-heading" class="mb-4 text-lg font-bold text-slate-950">Penugasan mengajar</h2>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th scope="col" class="px-5 py-3">Mata pelajaran</th>
                                    <th scope="col" class="px-5 py-3">Kelas</th>
                                    <th scope="col" class="px-5 py-3">Term</th>
                                    <th scope="col" class="px-5 py-3">Periode</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($teachingAssignments as $assignment)
                                    <tr>
                                        <td class="px-5 py-3">
                                            <span class="font-semibold text-slate-950">{{ $assignment->subject->name }}</span>
                                            <span class="ml-2 text-xs text-slate-500">{{ $assignment->subject->code }}</span>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600">{{ $assignment->class?->getFullName() ?? '—' }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $assignment->term?->label ?? 'Tanpa term' }}</td>
                                        <td class="whitespace-nowrap px-5 py-3 text-slate-600">
                                            {{ $assignment->starts_on?->format('d M Y') }} — {{ $assignment->ends_on?->format('d M Y') ?? 'berlangsung' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-600">Belum ada penugasan mengajar aktif.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section aria-labelledby="subjects-heading" class="mb-8">
                <h2 id="subjects-heading" class="mb-4 text-lg font-bold text-slate-950">Mata pelajaran yang diampu</h2>
                <div class="flex flex-wrap gap-2">
                    @forelse ($assignedSubjects as $subject)
                        <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 shadow-sm">{{ $subject->code }} · {{ $subject->name }}</span>
                    @empty
                        <p class="text-sm text-slate-600">Belum ada mata pelajaran yang diampu.</p>
                    @endforelse
                </div>
            </section>
        @endif

        <section aria-labelledby="teacher-links-heading" class="mb-8">
            <h2 id="teacher-links-heading" class="mb-4 text-lg font-bold text-slate-950">Tautan berguna</h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($quickActions as $action)
                    @if (Route::has($action['route']))
                        <a
                            href="{{ route($action['route']) }}"
                            class="rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold text-slate-900 shadow-sm transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"
                        >{{ $action['label'] }}</a>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    <x-notification-feed :notifications="$recentNotifications" :unread="$unreadNotificationCount" />
@endsection

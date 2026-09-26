@extends('layouts.app')

@section('title', 'Dashboard administrator · Sekolah Kita')

@section('content')
    @php
        $yearLabel = $activeAcademicYear?->label ?? 'Belum ada';
        $yearHint = $activeAcademicYear
            ? $activeAcademicYear->starts_on->format('d M Y').' — '.$activeAcademicYear->ends_on->format('d M Y')
            : 'Aktifkan satu tahun ajaran untuk memulai.';
        $classHint = $activeClassCount.' kelas aktif dari '.$classCount.'.';
        $teacherHint = $activeTeacherCount.' guru berstatus aktif.';
        $studentHint = $activeStudentCount.' siswa berstatus aktif.';
        $periodLabel = $activePeriod?->name ?? 'Belum ada';
        $periodHint = $activePeriod
            ? $activePeriod->status->label().' · ditutup '.$activePeriod->closes_at->translatedFormat('d M Y')
            : 'Buat periode agar formulir publik dapat dibuka.';
        $applicationHint = $openPeriodCount.' periode berstatus dibuka.';
        $duplicateHint = $duplicateFlagCount > 0
            ? $duplicateFlagCount.' pengajuan ditandai duplikat.'
            : 'Tidak ada penanda duplikat.';
        $newsHint = $draftNewsCount.' berita masih draf.';
        $healthHint = 'Database: '.($health['checks']['database'] ? 'baik' : 'gagal');
        $healthHint .= ' · cache: '.($health['checks']['cache'] ? 'baik' : 'gagal');
        $healthHint .= ' · antrean: '.($health['checks']['queue'] ? 'baik' : 'gagal');
        $healthLabel = $health['healthy'] ? 'Sehat' : 'Perlu perhatian';
        $latestAudit = $recentAuditEvents->first();
        $auditLabel = $latestAudit === null ? '—' : ($latestAudit->occurred_at?->diffForHumans() ?? '—');
    @endphp

    <x-admin.page-header
        eyebrow="Administrator"
        title="Ringkasan sekolah"
        :description="'Selamat datang, '.auth()->user()->name.'. Semua angka di bawah dihitung langsung dari data yang tersimpan.'"
    >
        <x-slot:actions>
            <a href="{{ route('admin.teachers.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah guru</a>
            <a href="{{ route('admin.students.create') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah siswa</a>
        </x-slot:actions>
    </x-admin.page-header>

    {{-- Academic foundation: every other screen depends on these rows. --}}
    <section aria-labelledby="foundation-heading" class="mb-8">
        <h2 id="foundation-heading" class="sr-only">Fondasi akademik</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-admin.stat-card
                label="Tahun ajaran aktif"
                :value="$yearLabel"
                :hint="$yearHint"
                :tone="$activeAcademicYear ? 'accent' : 'warning'"
            >
                <a href="{{ route('admin.academic-years.index') }}" class="mt-3 inline-block text-xs font-semibold text-cyan-700 hover:text-cyan-900">Kelola tahun ajaran</a>
            </x-admin.stat-card>

            <x-admin.stat-card label="Term aktif" :value="$activeTermCount" hint="Term yang sedang berjalan." tone="muted" />
            <x-admin.stat-card label="Kelas" :value="$classCount" :hint="$classHint" />
            <x-admin.stat-card label="Mata pelajaran" :value="$subjectCount" hint="Dipakai pada penugasan mengajar." />
        </div>
    </section>

    {{-- People --}}
    <section aria-labelledby="people-heading" class="mb-8">
        <h2 id="people-heading" class="mb-4 text-lg font-bold text-slate-950">Guru dan siswa</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-admin.stat-card label="Total guru" :value="$teacherCount" :hint="$teacherHint">
                <a href="{{ route('admin.teachers.index') }}" class="mt-3 inline-block text-xs font-semibold text-cyan-700 hover:text-cyan-900">Kelola guru</a>
            </x-admin.stat-card>
            <x-admin.stat-card label="Total siswa" :value="$studentCount" :hint="$studentHint">
                <a href="{{ route('admin.students.index') }}" class="mt-3 inline-block text-xs font-semibold text-cyan-700 hover:text-cyan-900">Kelola siswa</a>
            </x-admin.stat-card>
            <x-admin.stat-card label="Enrollment aktif" :value="$enrollmentCount" hint="Penempatan siswa pada kelas.">
                <a href="{{ route('admin.enrollments.index') }}" class="mt-3 inline-block text-xs font-semibold text-cyan-700 hover:text-cyan-900">Kelola enrollment</a>
            </x-admin.stat-card>
            <x-admin.stat-card label="Administrator aktif" :value="$administratorCount" hint="Akun dengan akses konfigurasi." />
        </div>
    </section>

    {{-- PPDB --}}
    <section aria-labelledby="ppdb-heading" class="mb-8">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 id="ppdb-heading" class="text-lg font-bold text-slate-950">PPDB</h2>
            <a href="{{ route('admin.ppdb.periods.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Kelola periode</a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-admin.stat-card
                label="Periode aktif"
                :value="$periodLabel"
                :hint="$periodHint"
                :tone="$activePeriod !== null && $activePeriod->status->value === 'open' ? 'accent' : 'warning'"
            />
            <x-admin.stat-card label="Total pengajuan" :value="$applicationCount" :hint="$applicationHint">
                <a href="{{ route('admin.ppdb.applications.index') }}" class="mt-3 inline-block text-xs font-semibold text-cyan-700 hover:text-cyan-900">Lihat pendaftar</a>
            </x-admin.stat-card>
            <x-admin.stat-card
                label="Menunggu review"
                :value="$awaitingReviewCount"
                :hint="$duplicateHint"
                :tone="$awaitingReviewCount > 0 ? 'warning' : 'muted'"
            >
                <a href="{{ route('admin.ppdb.review.index', ['actionable' => 1]) }}" class="mt-3 inline-block text-xs font-semibold text-cyan-700 hover:text-cyan-900">Masuk antrean review</a>
            </x-admin.stat-card>
            <x-admin.stat-card
                label="Menunggu konversi"
                :value="$pendingConversionCount"
                hint="Pengajuan diterima yang belum menjadi data siswa."
            />
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3">
                <h3 class="text-sm font-semibold text-slate-950">Pengajuan terbaru</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-5 py-3">Referensi</th>
                            <th scope="col" class="px-5 py-3">Periode</th>
                            <th scope="col" class="px-5 py-3">Status</th>
                            <th scope="col" class="px-5 py-3">Dikirim</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentApplications as $application)
                            <tr>
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.ppdb.applications.show', $application) }}" class="font-mono font-semibold text-cyan-700 hover:text-cyan-900">{{ $application->registration_number }}</a>
                                    @if ($application->duplicate_flag)
                                        <span class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Duplikat</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-slate-600">{{ $application->period?->name ?? '—' }}</td>
                                <td class="px-5 py-3"><x-admin.status-badge :status="$application->status" /></td>
                                <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $application->submitted_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-slate-600">
                                    Belum ada pengajuan PPDB. <a href="{{ route('admin.ppdb.periods.create') }}" class="font-semibold text-cyan-700 hover:text-cyan-900">Buat periode PPDB</a> untuk mulai menerima pendaftar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- Content and system --}}
    <section aria-labelledby="system-heading" class="mb-8">
        <h2 id="system-heading" class="mb-4 text-lg font-bold text-slate-950">Konten dan sistem</h2>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.stat-card label="Berita terbit" :value="$publishedNewsCount" :hint="$newsHint">
                    <a href="{{ route('admin.news.index') }}" class="mt-3 inline-block text-xs font-semibold text-cyan-700 hover:text-cyan-900">Kelola berita</a>
                </x-admin.stat-card>
                <x-admin.stat-card label="Notifikasi belum dibaca" :value="$unreadNotificationCount" hint="Untuk akun Anda sendiri.">
                    <a href="{{ route('notifications.index') }}" class="mt-3 inline-block text-xs font-semibold text-cyan-700 hover:text-cyan-900">Buka notifikasi</a>
                </x-admin.stat-card>
                <x-admin.stat-card
                    label="Kesiapan sistem"
                    :value="$healthLabel"
                    :hint="$healthHint"
                    :tone="$health['healthy'] ? 'muted' : 'warning'"
                >
                    <a href="{{ route('health.ready') }}" class="mt-3 inline-block text-xs font-semibold text-cyan-700 hover:text-cyan-900">Lihat laporan kesiapan</a>
                </x-admin.stat-card>
                <x-admin.stat-card label="Audit terakhir" :value="$auditLabel" hint="Aktivitas administrasi terakhir." />
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-3">
                    <h3 class="text-sm font-semibold text-slate-950">Audit terbaru</h3>
                    <a href="{{ route('admin.audit-logs.index') }}" class="text-xs font-semibold text-cyan-700 hover:text-cyan-900">Semua audit log</a>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($recentAuditEvents as $event)
                        <li class="px-5 py-3">
                            <p class="font-mono text-xs text-slate-900">{{ $event->action }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $event->actor?->name ?? 'sistem' }} · {{ $event->target_type }}@{{ $event->target_id }} · {{ $event->occurred_at?->diffForHumans() }} · {{ $event->result }}
                            </p>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-slate-600">Belum ada aktivitas audit.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </section>

    <section aria-labelledby="actions-heading" class="mb-8">
        <h2 id="actions-heading" class="mb-4 text-lg font-bold text-slate-950">Aksi cepat</h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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

    @if ($activeAcademicYear === null)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            <p class="font-semibold">Fondasi akademik belum lengkap.</p>
            <p class="mt-2 leading-6">
                Tanpa tahun ajaran aktif, kelas, enrollment, assignment, dan periode PPDB tidak dapat dibuat.
                Mulai dari <a href="{{ route('admin.academic-years.create') }}" class="font-semibold underline">membuat tahun ajaran</a>.
            </p>
        </div>
    @endif
@endsection

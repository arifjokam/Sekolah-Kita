@extends('layouts.app')

@section('title', 'Dashboard siswa · Sekolah Kita')

@section('content')
    @php
        $welcome = 'Selamat datang, '.auth()->user()->name.'. Halaman ini hanya menampilkan data Anda sendiri.';

        if ($hasProfile) {
            $identityHint = 'Nomor induk: '.$student->student_number;
            $statusHint = 'Masuk sejak '.($student->admission_date?->format('d M Y') ?? '—').'.';
            $classLabel = $class?->getFullName() ?? 'Belum ada enrollment';
            $classHint = $enrollment
                ? 'Sejak '.($enrollment->effective_from?->format('d M Y') ?? '—').'.'
                : 'Hubungi administrator bila kelas belum dibagikan.';
            $yearLabel = $activeAcademicYear?->label ?? 'Belum ada';
            $yearHint = $activeAcademicYear
                ? $activeAcademicYear->code
                : 'Administrator belum mengaktifkan tahun ajaran.';
        }
    @endphp

    <x-admin.page-header
        eyebrow="Siswa"
        title="Informasi siswa"
        :description="$welcome"
    />

    @unless ($hasProfile)
        <x-admin.empty-state
            label="Data siswa belum tersedia"
            message="Akun ini memiliki peran siswa tetapi belum terhubung ke data siswa. Administrator perlu menautkan akun ini ke record siswa, termasuk enrollment dan kelas, sebelum informasi dapat ditampilkan."
        >
            <span class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Hubungi administrator sekolah</span>
        </x-admin.empty-state>
    @endunless

    @if ($hasProfile)
        <section aria-labelledby="identity-heading" class="mb-8">
            <h2 id="identity-heading" class="sr-only">Identitas siswa</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-admin.stat-card label="Nama" :value="$student->getDisplayName()" :hint="$identityHint" tone="accent" />
                <x-admin.stat-card label="Status siswa" :value="$student->status->label()" :hint="$statusHint" />
                <x-admin.stat-card
                    label="Kelas"
                    :value="$classLabel"
                    :hint="$classHint"
                    :tone="$class ? 'muted' : 'warning'"
                />
                <x-admin.stat-card
                    label="Tahun ajaran"
                    :value="$yearLabel"
                    :hint="$yearHint"
                    :tone="$activeAcademicYear ? 'muted' : 'warning'"
                />
            </div>
        </section>

        @if ($enrollment === null)
            <div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-semibold">Enrollment belum tersedia.</p>
                <p class="mt-2 leading-6">
                    Anda belum terdaftar pada kelas mana pun untuk tahun ajaran berjalan. Hubungi bagian tata usaha sekolah
                    untuk mendapatkan penempatan kelas.
                </p>
            </div>
        @endif

        @if ($guardians->isNotEmpty())
            <section aria-labelledby="guardian-heading" class="mb-8">
                <h2 id="guardian-heading" class="mb-4 text-lg font-bold text-slate-950">Wali</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($guardians as $link)
                        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="font-semibold text-slate-950">{{ $link->legal_name }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ \App\Enums\GuardianRelationshipType::tryFrom((string) $link->pivot->relationship_type)?->label() ?? 'Wali' }}</p>
                            <dl class="mt-3 space-y-1 text-sm text-slate-600">
                                @if ($link->phone)<div class="flex gap-2"><dt class="text-slate-500">Telepon</dt><dd>{{ $link->phone }}</dd></div>@endif
                                @if ($link->email)<div class="flex gap-2"><dt class="text-slate-500">Email</dt><dd class="truncate">{{ $link->email }}</dd></div>@endif
                            </dl>
                            <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ $link->pivot->is_primary_contact ? 'Kontak utama' : 'Kontak pendukung' }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-labelledby="announcement-heading" class="mb-8">
            <div class="mb-4 flex items-center justify-between gap-4">
                <h2 id="announcement-heading" class="text-lg font-bold text-slate-950">Pengumuman sekolah</h2>
                <a href="{{ route('news.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Semua berita</a>
            </div>
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($announcements as $announcement)
                    <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $announcement->published_at?->translatedFormat('d M Y') }}</p>
                        <h3 class="mt-2 font-semibold text-slate-950">
                            <a href="{{ route('news.show', $announcement->slug) }}" class="hover:text-cyan-800">{{ $announcement->title }}</a>
                        </h3>
                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">{{ $announcement->excerpt }}</p>
                    </li>
                @empty
                    <li class="sm:col-span-2 lg:col-span-3">
                        <x-admin.empty-state label="Belum ada pengumuman" message="Sekolah belum menerbitkan berita. Kunjungi halaman berita untuk kabar terbaru." >
                            <a href="{{ route('news.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Buka halaman berita</a>
                        </x-admin.empty-state>
                    </li>
                @endforelse
            </ul>
        </section>

        @if ($enrollmentHistory->isNotEmpty())
            <section aria-labelledby="history-heading" class="mb-8">
                <h2 id="history-heading" class="mb-4 text-lg font-bold text-slate-950">Riwayat kelas</h2>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th scope="col" class="px-5 py-3">Kelas</th>
                                    <th scope="col" class="px-5 py-3">Tahun ajaran</th>
                                    <th scope="col" class="px-5 py-3">Status</th>
                                    <th scope="col" class="px-5 py-3">Mulai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($enrollmentHistory as $history)
                                    <tr>
                                        <td class="px-5 py-3 font-medium text-slate-950">{{ $history->class?->getFullName() ?? '—' }}</td>
                                        <td class="px-5 py-3 text-slate-600">{{ $history->academicYear?->label ?? '—' }}</td>
                                        <td class="px-5 py-3"><x-admin.status-badge :status="$history->status" /></td>
                                        <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $history->effective_from?->format('d M Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        <section aria-labelledby="student-links-heading" class="mb-8">
            <h2 id="student-links-heading" class="mb-4 text-lg font-bold text-slate-950">Tautan berguna</h2>
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
    @endif

    <x-notification-feed :notifications="$recentNotifications" :unread="$unreadNotificationCount" />
@endsection

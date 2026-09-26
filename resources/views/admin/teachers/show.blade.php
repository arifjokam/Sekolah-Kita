@extends('layouts.app')

@section('title', $teacher->legal_name.' · Data guru')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Guru', 'route' => 'admin.teachers.index'],
            ['label' => $teacher->legal_name],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$teacher->legal_name"
        :description="($teacher->preferred_name ? 'Dipanggil '.$teacher->preferred_name.'. ' : '').($teacher->position_title ?? 'Jabatan belum diisi.').' NIP '.$teacher->employee_number.'.'"
    >
        <x-slot:actions>
            <a href="{{ route('admin.teachers.edit', $teacher) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Edit data</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="identity-detail-heading">
                <h2 id="identity-detail-heading" class="text-lg font-bold text-slate-950">Identitas dan kontak</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama lengkap</dt><dd class="mt-1 text-sm text-slate-900">{{ $teacher->legal_name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama panggilan</dt><dd class="mt-1 text-sm text-slate-900">{{ $teacher->preferred_name ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nomor identitas</dt><dd class="mt-1 font-mono text-sm text-slate-900">{{ $teacher->national_id ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis kelamin</dt><dd class="mt-1 text-sm text-slate-900">{{ $teacher->gender === 'L' ? 'Laki-laki' : ($teacher->gender === 'P' ? 'Perempuan' : '—') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tempat, tanggal lahir</dt><dd class="mt-1 text-sm text-slate-900">{{ $teacher->birth_place ?? '—' }}{{ $teacher->birth_date ? ', '.$teacher->birth_date->format('d M Y') : '' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt><dd class="mt-1 break-all text-sm text-slate-900">{{ $teacher->email ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telepon</dt><dd class="mt-1 text-sm text-slate-900">{{ $teacher->phone ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alamat</dt><dd class="mt-1 text-sm leading-6 text-slate-900">{{ $teacher->address ?? '—' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="homeroom-heading">
                <h2 id="homeroom-heading" class="text-lg font-bold text-slate-950">Wali kelas aktif</h2>
                <ul class="mt-4 space-y-2">
                    @forelse ($teacher->activeClassTeacherAssignments as $assignment)
                        <li class="rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <a href="{{ route('admin.classes.show', $assignment->class) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $assignment->class?->getFullName() }}</a>
                            <span class="text-slate-500"> · {{ $assignment->class?->academicYear?->label ?? 'Tanpa tahun ajaran' }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ $assignment->starts_on?->format('d M Y') }} — {{ $assignment->ends_on?->format('d M Y') ?? 'berlangsung' }}</span>
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-600">
                            Belum menjadi wali kelas aktif.
                            <a href="{{ route('admin.class-teacher-assignments.create') }}" class="font-semibold text-cyan-700 hover:text-cyan-900">Buat penugasan</a>.
                        </li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="teaching-heading">
                <h2 id="teaching-heading" class="text-lg font-bold text-slate-950">Penugasan mengajar aktif</h2>
                <ul class="mt-4 space-y-2">
                    @forelse ($teacher->activeTeachingAssignments as $assignment)
                        <li class="rounded-xl border border-slate-200 px-4 py-3 text-sm">
                            <span class="font-semibold text-slate-950">{{ $assignment->subject?->name }}</span>
                            <span class="text-slate-500"> · {{ $assignment->class?->getFullName() }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ $assignment->academicYear?->label ?? '—' }} · {{ $assignment->starts_on?->format('d M Y') }} — {{ $assignment->ends_on?->format('d M Y') ?? 'berlangsung' }}</span>
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-600">
                            Belum ada penugasan mengajar aktif.
                            <a href="{{ route('admin.teaching-assignments.create') }}" class="font-semibold text-cyan-700 hover:text-cyan-900">Tambahkan assignment</a>.
                        </li>
                    @endforelse
                </ul>
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="status-heading">
                <h2 id="status-heading" class="text-lg font-bold text-slate-950">Status kepegawaian</h2>
                <p class="mt-3"><x-admin.status-badge :status="$teacher->employment_status->value" /></p>
                <p class="mt-3 text-sm text-slate-600">
                    {{ $teacher->employment_start_date?->format('d M Y') }} — {{ $teacher->employment_end_date?->format('d M Y') ?? 'berlangsung' }}
                </p>

                <form method="POST" action="{{ route('admin.teachers.employment-status.update', $teacher) }}" class="mt-4 flex items-end gap-2">
                    @csrf
                    @method('PATCH')
                    <div class="min-w-0 flex-1">
                        <label for="employment_status" class="block text-sm font-medium text-slate-700">Ubah status</label>
                        <select id="employment_status" name="employment_status" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($status === $teacher->employment_status)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Simpan</button>
                </form>

                <p class="mt-3 text-xs leading-5 text-slate-500">
                    Guru yang tidak aktif otomatis kehilangan akses portal bila memiliki akun tertaut.
                </p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="account-heading">
                <h2 id="account-heading" class="text-lg font-bold text-slate-950">Akun portal</h2>
                @if ($teacher->user)
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Nama</dt><dd class="font-medium text-slate-900">{{ $teacher->user->name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Email</dt><dd class="break-all font-medium text-slate-900">{{ $teacher->user->email }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Status akun</dt><dd><x-admin.status-badge :status="$teacher->user->status?->value ?? 'inactive'" /></dd></div>
                    </dl>
                @else
                    <p class="mt-3 rounded-xl border border-dashed border-slate-300 px-4 py-5 text-sm leading-6 text-slate-600">
                        Guru ini belum memiliki akun portal. Akun sengaja tidak dibuat otomatis dari master data.
                    </p>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="public-heading">
                <h2 id="public-heading" class="text-lg font-bold text-slate-950">Profil publik</h2>
                <p class="mt-3 text-sm text-slate-600">
                    {{ $teacher->is_publicly_visible ? 'Profil guru sudah tayang di direktori publik.' : 'Profil guru belum ditayangkan di direktori publik.' }}
                </p>
                <a href="{{ route('admin.teacher-directory.edit', $teacher) }}" class="mt-4 inline-block rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Kelola profil publik</a>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="profile-text-heading">
                <h2 id="profile-text-heading" class="text-lg font-bold text-slate-950">Ringkasan profesional</h2>
                <p class="mt-3 text-sm leading-6 text-slate-700">{{ $teacher->qualification_summary ?? 'Ringkasan kualifikasi belum diisi.' }}</p>
                <p class="mt-3 text-sm leading-6 text-slate-700">{{ $teacher->biography ?? 'Biografi belum diisi.' }}</p>
            </section>
        </div>
    </div>
@endsection

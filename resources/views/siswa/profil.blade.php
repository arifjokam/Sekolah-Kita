@extends('layouts.app')

@section('title', 'Biodata siswa · Sekolah Kita')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.siswa'],
            ['label' => 'Biodata siswa'],
        ];

        $gender = match ($student->gender) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '—',
        };
    @endphp

    <x-admin.page-header
        eyebrow="Siswa"
        title="Biodata siswa"
        description="Data resmi dikelola administrator. Anda dapat memperbarui nomor telepon dan alamat karena keduanya dipakai untuk menghubungi Anda."
    />

    <x-form.error-summary />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Nama" :value="$student->getDisplayName()" :hint="$student->student_number ?? 'NIS belum diisi'" tone="accent" />
        <x-admin.stat-card label="Kelas" :value="$class?->getFullName() ?? 'Belum ada enrollment'" :tone="$class ? 'muted' : 'warning'" />
        <x-admin.stat-card label="Tahun ajaran" :value="$activeAcademicYear?->label ?? 'Belum ada'" :tone="$activeAcademicYear ? 'muted' : 'warning'" />
        <x-admin.stat-card label="Status siswa" :value="$student->status->label()" :hint="$student->admission_date ? 'Masuk '. $student->admission_date->format('d M Y') : null" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="account-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="account-heading" class="text-lg font-bold text-slate-950">Informasi akun</h2>
            <p class="mt-2 text-sm text-slate-600">Alamat email adalah identitas masuk Anda dan hanya dapat diubah administrator.</p>

            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama akun</dt><dd class="mt-1 text-slate-900">{{ $user->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email akun</dt><dd class="mt-1 break-all text-slate-900">{{ $user->email }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Peran</dt><dd class="mt-1 text-slate-900">{{ $user->role?->label() }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status akun</dt><dd class="mt-1 text-slate-900">{{ $user->status->label() }}</dd></div>
            </dl>
        </section>

        <section aria-labelledby="identity-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="identity-heading" class="text-lg font-bold text-slate-950">Data resmi siswa</h2>
            <p class="mt-2 text-sm text-slate-600">Hanya dibaca. Data ini menjadi dasar administrasi sekolah.</p>

            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama lengkap</dt><dd class="mt-1 text-slate-900">{{ $student->legal_name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">NIS</dt><dd class="mt-1 text-slate-900">{{ $student->student_number ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">NISN</dt><dd class="mt-1 text-slate-900">{{ $student->nisn ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">NIK</dt><dd class="mt-1 text-slate-900">{{ $student->national_id ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tempat, tanggal lahir</dt><dd class="mt-1 text-slate-900">{{ $student->birth_place ?? '—' }}{{ $student->birth_date ? ', '.$student->birth_date->format('d M Y') : '' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis kelamin</dt><dd class="mt-1 text-slate-900">{{ $gender }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal masuk</dt><dd class="mt-1 text-slate-900">{{ $student->admission_date?->format('d M Y') ?? '—' }}</dd></div>
            </dl>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="contact-heading" class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="contact-heading" class="text-lg font-bold text-slate-950">Kontak saya</h2>
            <p class="mt-2 text-sm text-slate-600">Dua bidang ini boleh Anda ubah sendiri.</p>

            <form method="POST" action="{{ route('siswa.profil.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <x-form.text name="phone" type="tel" label="Nomor telepon" maxlength="30" :value="$student->phone" />
                <x-form.textarea name="address" label="Alamat" rows="3" maxlength="500" :value="$student->address" />

                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Simpan kontak</button>
            </form>
        </section>

        <div class="space-y-6">
            <section aria-labelledby="enrollment-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="enrollment-heading" class="text-lg font-bold text-slate-950">Enrollment berjalan</h2>
                @if ($enrollment)
                    <dl class="mt-4 space-y-3 text-sm">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kelas</dt><dd class="mt-1 text-slate-900">{{ $enrollment->class?->getFullName() }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tahun ajaran</dt><dd class="mt-1 text-slate-900">{{ $enrollment->academicYear?->label ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt><dd class="mt-1"><x-admin.status-badge :status="$enrollment->status->value" /></dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Efektif sejak</dt><dd class="mt-1 text-slate-900">{{ $enrollment->effective_from?->format('d M Y') ?? '—' }}</dd></div>
                    </dl>
                @else
                    <p class="mt-3 text-sm text-slate-600">Anda belum memiliki enrollment aktif. Hubungi bagian tata usaha sekolah.</p>
                @endif
            </section>

            <section aria-labelledby="guardian-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="guardian-heading" class="text-lg font-bold text-slate-950">Wali</h2>
                <ul class="mt-4 space-y-2">
                    @forelse ($guardians as $link)
                        <li class="rounded-xl border border-slate-200 p-3 text-sm">
                            <p class="font-semibold text-slate-900">{{ $link->legal_name }}</p>
                            <p class="mt-1 text-xs text-slate-600">
                                {{ \App\Enums\GuardianRelationshipType::tryFrom((string) $link->pivot->relationship_type)?->label() ?? 'Wali' }}
                                @if ($link->phone) · {{ $link->phone }} @endif
                            </p>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ $link->pivot->is_primary_contact ? 'Kontak utama' : 'Kontak pendukung' }}
                            </p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">Data wali belum tersedia.</li>
                    @endforelse
                </ul>
            </section>

            <section aria-labelledby="history-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="history-heading" class="text-lg font-bold text-slate-950">Riwayat kelas</h2>
                <ul class="mt-4 space-y-2">
                    @forelse ($history as $entry)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3 text-sm">
                            <span>
                                <span class="font-semibold text-slate-900">{{ $entry->class?->getFullName() }}</span>
                                <span class="text-xs text-slate-500"> · {{ $entry->academicYear?->label }}</span>
                            </span>
                            <x-admin.status-badge :status="$entry->status->value" />
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">Belum ada riwayat kelas.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endsection

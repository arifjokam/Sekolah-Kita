@extends('layouts.app')

@section('title', 'Biodata guru · Sekolah Kita')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.guru'],
            ['label' => 'Biodata guru'],
        ];

        $gender = match ($teacher->gender) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            default => '—',
        };
    @endphp

    <x-admin.page-header
        eyebrow="Guru"
        title="Biodata guru"
        description="Data resmi dikelola administrator. Anda dapat memperbarui nomor telepon dan alamat karena keduanya dipakai untuk menghubungi Anda."
    />

    <x-form.error-summary />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Nama" :value="$teacher->getDisplayName()" :hint="$teacher->position_title ?? 'Jabatan belum diisi'" tone="accent" />
        <x-admin.stat-card label="NIP" :value="$teacher->employee_number ?? '—'" />
        <x-admin.stat-card label="Status kepegawaian" :value="$teacher->employment_status->label()" :hint="$teacher->employment_start_date?->format('d M Y') ? 'Mulai '. $teacher->employment_start_date->format('d M Y') : null" />
        <x-admin.stat-card label="Kelas diampu" :value="$classes->count()" hint="Kelas dengan assignment mengajar atau wali kelas." />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="account-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="account-heading" class="text-lg font-bold text-slate-950">Informasi akun</h2>
            <p class="mt-2 text-sm text-slate-600">Alamat email adalah identitas masuk Anda dan hanya dapat diubah administrator.</p>

            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama akun</dt><dd class="mt-1 text-slate-900">{{ $user->name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt><dd class="mt-1 break-all text-slate-900">{{ $user->email }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Peran</dt><dd class="mt-1 text-slate-900">{{ $user->role?->label() }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status akun</dt><dd class="mt-1 text-slate-900">{{ $user->status->label() }}</dd></div>
            </dl>
        </section>

        <section aria-labelledby="identity-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="identity-heading" class="text-lg font-bold text-slate-950">Data resmi guru</h2>
            <p class="mt-2 text-sm text-slate-600">Hanya dibaca. Data ini menjadi dasar administrasi dan tidak dapat diubah dari akun guru.</p>

            <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama lengkap</dt><dd class="mt-1 text-slate-900">{{ $teacher->legal_name }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">NIP</dt><dd class="mt-1 text-slate-900">{{ $teacher->employee_number ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">NUPTK</dt><dd class="mt-1 text-slate-900">{{ $teacher->national_id ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tempat, tanggal lahir</dt><dd class="mt-1 text-slate-900">{{ $teacher->birth_place ?? '—' }}{{ $teacher->birth_date ? ', '.$teacher->birth_date->format('d M Y') : '' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis kelamin</dt><dd class="mt-1 text-slate-900">{{ $gender }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jabatan</dt><dd class="mt-1 text-slate-900">{{ $teacher->position_title ?? '—' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Mulai bekerja</dt><dd class="mt-1 text-slate-900">{{ $teacher->employment_start_date?->format('d M Y') ?? '—' }}</dd></div>
            </dl>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="contact-heading" class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="contact-heading" class="text-lg font-bold text-slate-950">Kontak saya</h2>
            <p class="mt-2 text-sm text-slate-600">Dua bidang ini boleh Anda ubah sendiri.</p>

            <form method="POST" action="{{ route('guru.profil.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <x-form.text name="phone" type="tel" label="Nomor telepon" maxlength="30" :value="$teacher->phone" />
                <x-form.textarea name="address" label="Alamat" rows="3" maxlength="500" :value="$teacher->address" />

                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Simpan kontak</button>
            </form>
        </section>

        <div class="space-y-6">
            <section aria-labelledby="subjects-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="subjects-heading" class="text-lg font-bold text-slate-950">Mata pelajaran yang diampu</h2>
                <ul class="mt-4 flex flex-wrap gap-2">
                    @forelse ($subjects as $subject)
                        <li class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-800 shadow-sm">
                            {{ $subject->code }} · {{ $subject->name }}
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">Belum ada mata pelajaran yang diampu.</li>
                    @endforelse
                </ul>
            </section>

            <section aria-labelledby="assignment-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="assignment-heading" class="text-lg font-bold text-slate-950">Assignment mengajar</h2>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Assignment mengajar</caption>
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-2">Mata pelajaran</th>
                                <th scope="col" class="px-4 py-2">Kelas</th>
                                <th scope="col" class="px-4 py-2">Semester</th>
                                <th scope="col" class="px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($teachingAssignments as $assignment)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-950">{{ $assignment->subject?->name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $assignment->class?->getFullName() ?? '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $assignment->term?->semesterLabel() ?? '—' }}</td>
                                    <td class="px-4 py-3"><x-admin.status-badge :status="$assignment->status->value" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-600">Belum ada assignment mengajar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="homeroom-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="homeroom-heading" class="text-lg font-bold text-slate-950">Wali kelas</h2>
                <ul class="mt-4 space-y-2">
                    @forelse ($homeroomAssignments as $assignment)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3 text-sm">
                            <span class="font-semibold text-slate-900">{{ $assignment->class?->getFullName() }}</span>
                            <x-admin.status-badge :status="$assignment->status->value" />
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">Anda bukan wali kelas pada tahun ajaran ini.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endsection

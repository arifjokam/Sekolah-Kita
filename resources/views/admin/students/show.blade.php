@extends('layouts.app')

@section('title', $student->legal_name.' · Data siswa')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Siswa', 'route' => 'admin.students.index'],
            ['label' => $student->legal_name],
        ];

        $pageDescription = 'NIS '.$student->student_number;

        if (filled($student->nisn)) {
            $pageDescription .= ' · NISN '.$student->nisn;
        }

        $relationshipOptions = collect(\App\Enums\GuardianRelationshipType::cases())
            ->mapWithKeys(fn ($type) => [$type->value => $type->label()]);
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        :title="$student->legal_name"
        :description="$pageDescription"
    >
        <x-slot:actions>
            <a href="{{ route('admin.enrollments.create', ['student_id' => $student->id]) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah enrollment</a>
            <a href="{{ route('admin.students.edit', $student) }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Edit data</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr]">
        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="enrollment-heading">
                <h2 id="enrollment-heading" class="text-lg font-bold text-slate-950">Enrollment</h2>
                <ul class="mt-4 space-y-2">
                    @forelse ($student->enrollments as $enrollment)
                        <li class="flex flex-col gap-2 rounded-xl border border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <a href="{{ route('admin.enrollments.show', $enrollment) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">{{ $enrollment->class?->getFullName() ?? 'Kelas dihapus' }}</a>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $enrollment->academicYear?->label ?? '—' }} · {{ $enrollment->effective_from?->format('d M Y') ?? '—' }} — {{ $enrollment->effective_to?->format('d M Y') ?? 'berlangsung' }}
                                </p>
                            </div>
                            <x-admin.status-badge :status="$enrollment->status->value" />
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm leading-6 text-slate-600">
                            Belum ada enrollment. Tambahkan penempatan kelas agar siswa muncul pada dashboard siswa.
                        </li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="guardian-heading">
                <h2 id="guardian-heading" class="text-lg font-bold text-slate-950">Wali</h2>

                <ul class="mt-4 space-y-2">
                    @forelse ($student->guardians as $link)
                        <li class="flex flex-col gap-2 rounded-xl border border-slate-200 px-4 py-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-950">
                                    {{ $link->legal_name }}
                                    <span class="font-normal text-slate-500">({{ \App\Enums\GuardianRelationshipType::tryFrom((string) $link->pivot->relationship_type)?->label() ?? 'Wali' }})</span>
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $link->phone ?? 'Tanpa telepon' }} · {{ $link->email ?? 'tanpa email' }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $link->pivot->is_primary_contact ? 'Kontak utama' : 'Kontak pendukung' }}
                                    @if ($link->pivot->is_emergency_contact) · Kontak darurat @endif
                                    · sejak {{ \Illuminate\Support\Carbon::parse($link->pivot->starts_on)->translatedFormat('d M Y') }}
                                    @if ($link->pivot->ends_on) · berakhir {{ \Illuminate\Support\Carbon::parse($link->pivot->ends_on)->translatedFormat('d M Y') }} @endif
                                </p>
                            </div>
                            @if ($link->pivot->ends_on === null)
                                <form method="POST" action="{{ route('admin.students.guardians.destroy', [$student, $link->pivot->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50">Lepas wali</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-600">
                            Belum ada wali yang terhubung. Tambahkan wali pada formulir di bawah.
                        </li>
                    @endforelse
                </ul>

                <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-800">Tambah wali</summary>

                    <form method="POST" action="{{ route('admin.students.guardians.store', $student) }}" class="mt-4 space-y-4">
                        @csrf
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-form.text name="legal_name" label="Nama wali" required />
                            <x-form.select
                                name="relationship_type"
                                label="Hubungan"
                                required
                                :options="$relationshipOptions"
                            />
                            <x-form.text name="phone" label="Telepon" maxlength="30" />
                            <x-form.text name="email" label="Email" type="email" maxlength="150" />
                            <x-form.text name="national_id" label="Nomor identitas" maxlength="32" />
                            <x-form.date name="starts_on" label="Mulai berlaku" :value="now()->toDateString()" />
                        </div>

                        <x-form.textarea name="address" label="Alamat" :rows="2" maxlength="2000" />

                        <div class="flex flex-wrap items-center gap-4">
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="is_primary_contact" value="1" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600">
                                Kontak utama
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="is_emergency_contact" value="1" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-600">
                                Kontak darurat
                            </label>
                        </div>

                        <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambahkan wali</button>
                    </form>
                </details>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="identity-detail-heading">
                <h2 id="identity-detail-heading" class="text-lg font-bold text-slate-950">Identitas dan kontak</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama panggilan</dt><dd class="mt-1 text-sm text-slate-900">{{ $student->preferred_name ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jenis kelamin</dt><dd class="mt-1 text-sm text-slate-900">{{ $student->gender === 'L' ? 'Laki-laki' : ($student->gender === 'P' ? 'Perempuan' : '—') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tempat, tanggal lahir</dt><dd class="mt-1 text-sm text-slate-900">{{ $student->birth_place ?? '—' }}{{ $student->birth_date ? ', '.$student->birth_date->format('d M Y') : '' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal masuk</dt><dd class="mt-1 text-sm text-slate-900">{{ $student->admission_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt><dd class="mt-1 break-all text-sm text-slate-900">{{ $student->email ?? '—' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telepon</dt><dd class="mt-1 text-sm text-slate-900">{{ $student->phone ?? '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Alamat</dt><dd class="mt-1 text-sm leading-6 text-slate-900">{{ $student->address ?? '—' }}</dd></div>
                </dl>
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="lifecycle-heading">
                <h2 id="lifecycle-heading" class="text-lg font-bold text-slate-950">Status siswa</h2>
                <p class="mt-3"><x-admin.status-badge :status="$student->status->value" /></p>

                <form method="POST" action="{{ route('admin.students.lifecycle-status.update', $student) }}" class="mt-4 flex items-end gap-2">
                    @csrf
                    @method('PATCH')
                    <div class="min-w-0 flex-1">
                        <label for="status" class="block text-sm font-medium text-slate-700">Ubah status</label>
                        <select id="status" name="status" class="mt-1 block w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected($status === $student->status)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Simpan</button>
                </form>

                <p class="mt-3 text-xs leading-5 text-slate-500">
                    Status lulus, keluar, dan mutasi tidak dapat dipilih selama enrollment aktif masih berjalan.
                </p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="account-heading">
                <h2 id="account-heading" class="text-lg font-bold text-slate-950">Akun portal</h2>
                @if ($student->user)
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Nama</dt><dd class="font-medium text-slate-900">{{ $student->user->name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Email</dt><dd class="break-all font-medium text-slate-900">{{ $student->user->email }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Status</dt><dd><x-admin.status-badge :status="$student->user->status?->value ?? 'inactive'" /></dd></div>
                    </dl>
                @else
                    <p class="mt-3 rounded-xl border border-dashed border-slate-300 px-4 py-5 text-sm leading-6 text-slate-600">
                        Siswa ini belum memiliki akun portal. Akun tidak dibuat otomatis dari master data.
                    </p>
                @endif
            </section>
        </div>
    </div>
@endsection

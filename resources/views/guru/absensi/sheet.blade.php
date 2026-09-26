@extends('layouts.app')

@section('title', 'Isi absensi · '.($session->class?->getFullName() ?? 'Guru'))

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.guru'],
            ['label' => 'Absensi', 'route' => 'guru.absensi.index'],
            ['label' => $session->session_date->translatedFormat('d M Y')],
        ];

        $isCorrection = $session->isSubmitted();
        $action = $isCorrection
            ? route('guru.absensi.correct', $session)
            : route('guru.absensi.store', $session);
    @endphp

    <x-admin.page-header
        eyebrow="Absensi"
        :title="$session->class?->getFullName().' · '.$session->subject?->name"
        :description="$session->session_date->translatedFormat('l, d M Y').' · '.($session->starts_at ? substr((string) $session->starts_at, 0, 5) : '—').'–'.($session->ends_at ? substr((string) $session->ends_at, 0, 5) : '—').' · semester '.($session->term?->semesterLabel() ?? '—')"
    />

    <x-form.error-summary />

    <div class="grid gap-4 sm:grid-cols-4">
        <x-admin.stat-card label="Status sesi" :value="$session->status->label()" :tone="$isCorrection ? 'muted' : 'warning'" />
        <x-admin.stat-card label="Jumlah siswa" :value="$roster->count()" />
        <x-admin.stat-card label="Sudah tersimpan" :value="$existing->count()" />
        <x-admin.stat-card label="Diisi oleh" :value="$session->markedBy?->name ?? '—'" :hint="$session->submitted_at?->translatedFormat('d M Y H:i')" />
    </div>

    @if ($isCorrection)
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900" role="status">
            Sesi ini sudah tersimpan. Setiap perubahan disimpan sebagai koreksi, menambah revisi pada catatan absensi dan
            tercatat pada audit log.
        </div>
    @endif

    @if ($roster->isEmpty())
        <div class="mt-6">
            <x-admin.empty-state
                label="Belum ada siswa di kelas ini"
                message="Enrollment kelas ini masih kosong, sehingga tidak ada yang dapat diabsen. Hubungi administrator untuk menambahkan siswa ke kelas."
            />
        </div>
    @else
        <form method="POST" action="{{ $action }}" class="mt-6 space-y-5" data-attendance-sheet>
            @csrf
            @if ($isCorrection) @method('PUT') @endif

            <div class="flex flex-wrap items-center justify-end gap-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <button type="button" data-mark-all="present" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Tandai semua hadir</button>
                <button type="button" data-clear-all class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Kosongkan pilihan</button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Daftar kehadiran siswa</caption>
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-3">Siswa</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                                <th scope="col" class="px-5 py-3">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($roster as $student)
                                @php
                                    /** @var \App\Models\AttendanceRecord|null $record */
                                    $record = $existing->get($student->id);
                                    $current = $record?->status->value ?? \App\Enums\AttendanceStatus::Present->value;
                                @endphp
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-slate-950">{{ $student->getDisplayName() }}</p>
                                        <p class="text-xs text-slate-500">{{ $student->student_number ?? '—' }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <fieldset>
                                            <legend class="sr-only">Status kehadiran {{ $student->getDisplayName() }}</legend>
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($statuses as $status)
                                                    <label class="cursor-pointer">
                                                        <input type="radio" class="peer sr-only"
                                                               name="records[{{ $student->id }}][status]"
                                                               value="{{ $status->value }}"
                                                               @checked($current === $status->value)>
                                                        <span class="inline-flex rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 transition peer-checked:border-transparent peer-checked:bg-cyan-700 peer-checked:text-white hover:bg-slate-50 peer-focus-visible:ring-2 peer-focus-visible:ring-cyan-600">
                                                            {{ $status->label() }}
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>
                                    </td>
                                    <td class="px-5 py-4">
                                        <label class="sr-only" for="note-{{ $student->id }}">Catatan untuk {{ $student->getDisplayName() }}</label>
                                        <input id="note-{{ $student->id }}"
                                               type="text"
                                               name="records[{{ $student->id }}][note]"
                                               value="{{ $record?->note }}"
                                               maxlength="255"
                                               placeholder="Wajib untuk Izin, Sakit, Alpa"
                                               class="w-full rounded-lg border-slate-300 text-sm">
                                        @error('records.'.$student->id.'.note')
                                            <p class="mt-1 text-sm text-rose-700">{{ $message }}</p>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('guru.absensi.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Kembali</a>
                <button type="submit" class="rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                    {{ $isCorrection ? 'Simpan koreksi' : 'Simpan absensi' }}
                </button>
            </div>
        </form>
    @endif
@endsection

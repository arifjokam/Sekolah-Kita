@extends('layouts.app')

@section('title', 'Laporan absensi bulanan · Guru')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.guru'],
            ['label' => 'Absensi', 'route' => 'guru.absensi.index'],
            ['label' => 'Laporan bulanan'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Absensi"
        title="Laporan absensi bulanan"
        :description="'Rekap kehadiran untuk '.($class?->getFullName() ?? '—').' pada '.$month->translatedFormat('F Y').'. Hanya kelas yang Anda ampu yang dapat dilaporkan.'"
    >
        <x-slot:actions>
            @if ($class)
                <button type="button" onclick="window.print()" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cetak</button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-form.error-summary />

    <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4">
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            <span>Bulan</span>
            <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
        </label>
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            <span>Kelas</span>
            <select name="class_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                <option value="">Pilih kelas</option>
                @foreach ($classes as $option)
                    <option value="{{ $option->id }}" @selected((int) request('class_id') === $option->id)>
                        {{ $option->getFullName() }}
                    </option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            <span>Mata pelajaran (opsional)</span>
            <select name="subject_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                <option value="">Semua mata pelajaran</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected((int) request('subject_id') === $subject->id)>
                        {{ $subject->code }} · {{ $subject->name }}
                    </option>
                @endforeach
            </select>
        </label>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tampilkan</button>
            <a href="{{ route('guru.absensi.report') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Reset</a>
        </div>
    </form>

    @if ($classes->isEmpty())
        <x-admin.empty-state
            label="Belum ada kelas yang dapat dilaporkan"
            message="Anda belum memiliki assignment mengajar maupun wali kelas pada tahun ajaran ini, sehingga tidak ada data absensi yang dapat ditampilkan."
        />
    @elseif (! $class)
        <x-admin.empty-state
            label="Pilih kelas"
            message="Pilih kelas dan bulan untuk melihat rekap kehadiran siswa."
        />
    @elseif ($result['students'] === [])
        <x-admin.empty-state
            label="Belum ada data absensi"
            :message="'Belum ada sesi absensi pada '.$class->getFullName().' untuk '.$month->translatedFormat('F Y').'.'"
        />
    @else
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.stat-card label="Sesi absensi" :value="$result['session_count']" />
            <x-admin.stat-card label="Jumlah siswa" :value="count($result['students'])" />
            <x-admin.stat-card label="Periode" :value="$month->translatedFormat('F Y')" tone="accent" />
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <caption class="sr-only">Rekap kehadiran bulanan {{ $class->getFullName() }} {{ $month->translatedFormat('F Y') }}</caption>
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">Siswa</th>
                            @foreach ($statuses as $status)
                                <th scope="col" class="px-4 py-3 text-center">{{ $status->label() }}</th>
                            @endforeach
                            <th scope="col" class="px-4 py-3 text-center">Total</th>
                            <th scope="col" class="px-4 py-3 text-center">Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($result['students'] as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-slate-950">{{ $row['student']->getDisplayName() }}</p>
                                    <p class="text-xs text-slate-500">{{ $row['student']->student_number ?? '—' }}</p>
                                </td>
                                @foreach ($statuses as $status)
                                    <td class="px-4 py-3 text-center text-slate-700">{{ $row['counts'][$status->value] ?? 0 }}</td>
                                @endforeach
                                <td class="px-4 py-3 text-center font-semibold text-slate-900">{{ $row['counts']['total'] ?? 0 }}</td>
                                <td class="px-4 py-3 text-center font-semibold {{ ($row['counts']['percentage'] ?? 0) >= 80 ? 'text-emerald-700' : (($row['counts']['percentage'] ?? 0) >= 60 ? 'text-amber-700' : 'text-rose-700') }}">
                                    {{ number_format((float) ($row['counts']['percentage'] ?? 0), 1) }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 text-sm font-semibold text-slate-900">
                        <tr>
                            <td class="px-4 py-3">Total kelas</td>
                            @foreach ($statuses as $status)
                                <td class="px-4 py-3 text-center">{{ $result['totals'][$status->value] ?? 0 }}</td>
                            @endforeach
                            <td class="px-4 py-3 text-center">{{ $result['totals']['total'] ?? 0 }}</td>
                            <td class="px-4 py-3 text-center">—</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <p class="mt-4 text-xs leading-5 text-slate-500">
            Persentase kehadiran dihitung dari (Hadir + Terlambat) dibagi jumlah sesi absensi pada bulan tersebut.
            Izin dan sakit tetap dihitung sebagai tidak hadir pada persentase, namun tetap tercatat terpisah.
        </p>
    @endif
@endsection

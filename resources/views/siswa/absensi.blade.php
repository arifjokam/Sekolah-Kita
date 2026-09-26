@extends('layouts.app')

@section('title', 'Absensi saya · Sekolah Kita')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.siswa'],
            ['label' => 'Absensi saya'],
        ];

        $totals = $summary['totals'];
    @endphp

    <x-admin.page-header
        eyebrow="Siswa"
        title="Absensi saya"
        :description="$class ? 'Rekap kehadiran Anda pada kelas '.$class->getFullName().'. Hanya data Anda sendiri yang tampil di halaman ini.' : 'Rekap kehadiran Anda. Data muncul setelah Anda memiliki sesi absensi.'"
    />

    @if ($terms->isNotEmpty())
        <form method="GET" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
            <label class="grid gap-1 text-sm font-medium text-slate-700">
                <span>Semester</span>
                <select name="term_id" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    <option value="">Semua semester</option>
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}" @selected((int) $termId === $term->id)>
                            {{ $term->semesterLabel() }} ({{ $term->status->label() }})
                        </option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tampilkan</button>
                <a href="{{ route('siswa.absensi.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Reset</a>
            </div>
        </form>
    @endif

    @if ($summary['session_count'] === 0)
        <x-admin.empty-state
            label="Belum ada absensi"
            message="Belum ada sesi absensi yang tercatat untuk Anda. Data akan muncul setelah guru mengisi kehadiran pada pelajaran yang Anda ikuti."
        />
    @else
        <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ($statuses as $status)
                <x-admin.stat-card :label="$status->label()" :value="$totals[$status->value] ?? 0" />
            @endforeach
            <x-admin.stat-card
                label="Persentase"
                :value="number_format($summary['percentage'], 1).'%'"
                :tone="$summary['percentage'] >= 80 ? 'muted' : 'warning'"
                :hint="$summary['session_count'].' sesi'"
            />
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <section aria-labelledby="monthly-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="monthly-heading" class="text-lg font-bold text-slate-950">Rekap per bulan</h2>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Rekap kehadiran per bulan</caption>
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-4 py-2">Bulan</th>
                                <th scope="col" class="px-4 py-2 text-center">Sesi</th>
                                <th scope="col" class="px-4 py-2 text-center">Hadir + terlambat</th>
                                <th scope="col" class="px-4 py-2 text-center">Persentase</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($monthlyRows as $key => $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $key)->translatedFormat('F Y') }}</td>
                                    <td class="px-4 py-3 text-center text-slate-700">{{ $row['total'] }}</td>
                                    <td class="px-4 py-3 text-center text-slate-700">{{ $row['attended'] }}</td>
                                    <td class="px-4 py-3 text-center font-semibold {{ $row['percentage'] >= 80 ? 'text-emerald-700' : 'text-amber-700' }}">{{ number_format($row['percentage'], 1) }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-600">Belum ada data bulanan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section aria-labelledby="recent-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="recent-heading" class="text-lg font-bold text-slate-950">Absensi terbaru</h2>

                <ul class="mt-4 space-y-2">
                    @forelse ($summary['recent'] as $entry)
                        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 p-3 text-sm">
                            <span>
                                <span class="font-semibold text-slate-900">{{ $entry['date']?->translatedFormat('d M Y') ?? '—' }}</span>
                                @if ($entry['record']->note)
                                    <span class="mt-0.5 block text-xs text-slate-600">{{ $entry['record']->note }}</span>
                                @endif
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $entry['record']->status->badgeClass() }}">
                                {{ $entry['record']->status->label() }}
                            </span>
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">Belum ada catatan absensi.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    @endif
@endsection

@extends('layouts.app')

@section('title', 'Audit log · Sekolah Kita')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Administrator</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Audit log</h1>
                <p class="mt-2 text-sm text-slate-600">Catatan append-only untuk aktivitas keamanan dan administration.</p>
            </div>
            <a href="{{ route('admin.audit-logs.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Muat ulang</a>
        </header>

        <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4">
            <label class="grid gap-1 text-sm font-medium text-slate-700">Aksi<input name="action" value="{{ request('action') }}" class="rounded-lg border-slate-300 text-sm"></label>
            <label class="grid gap-1 text-sm font-medium text-slate-700">Hasil<select name="result" class="rounded-lg border-slate-300 text-sm"><option value="">Semua</option><option value="success" @selected(request('result') === 'success')>Berhasil</option><option value="denied" @selected(request('result') === 'denied')>Ditolak</option><option value="failed" @selected(request('result') === 'failed')>Gagal</option></select></label>
            <label class="grid gap-1 text-sm font-medium text-slate-700">Target<input name="target_type" value="{{ request('target_type') }}" class="rounded-lg border-slate-300 text-sm"></label>
            <button type="submit" class="self-end rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Filter</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Aksi</th><th class="px-4 py-3">Aktor</th><th class="px-4 py-3">Target</th><th class="px-4 py-3">Hasil</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-600"><time datetime="{{ $log->occurred_at?->toIso8601String() }}">{{ $log->occurred_at?->translatedFormat('d M Y H:i') }}</time></td>
                                <td class="px-4 py-3 font-medium text-slate-900"><a class="text-cyan-700 hover:text-cyan-900" href="{{ route('admin.audit-logs.show', $log) }}">{{ $log->action }}</a></td>
                                <td class="px-4 py-3 text-slate-600">{{ $log->actor?->name ?? 'Sistem' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $log->target_type }}@if($log->target_id) #{{ $log->target_id }}@endif</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $log->result === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $log->result }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-600">Belum ada audit event.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($logs->hasPages())<div class="border-t border-slate-100 px-4 py-4">{{ $logs->links() }}</div>@endif
        </div>
    </div>
@endsection

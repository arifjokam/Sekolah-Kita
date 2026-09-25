@extends('layouts.app')

@section('title', 'Periode PPDB · Admin')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">PPDB</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Periode penerimaan</h1>
                <p class="mt-2 text-sm text-slate-600">Kelola informasi publik dan status periode PPDB.</p>
            </div>
            <a href="{{ route('admin.ppdb.periods.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Tambah periode</a>
        </header>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Tahun ajaran</th><th class="px-4 py-3">Jendela</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($periods as $period)
                            <tr>
                                <td class="px-4 py-3"><a class="font-semibold text-cyan-700 hover:text-cyan-900" href="{{ route('admin.ppdb.periods.edit', $period) }}">{{ $period->name }}</a><p class="mt-1 text-xs text-slate-500">{{ $period->slug }}</p></td>
                                <td class="px-4 py-3 text-slate-600">{{ $period->academicYear?->code }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $period->opens_at?->translatedFormat('d M Y') }} — {{ $period->closes_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $period->status->label() }}</span></td>
                                <td class="px-4 py-3"><div class="flex flex-wrap gap-2"><a href="{{ route('admin.ppdb.periods.edit', $period) }}" class="text-xs font-semibold text-slate-700 hover:text-cyan-700">Edit</a>@if($period->status->value === 'draft')<form method="POST" action="{{ route('admin.ppdb.periods.open', $period) }}">@csrf<button class="text-xs font-semibold text-cyan-700">Buka</button></form>@elseif($period->status->value === 'open')<form method="POST" action="{{ route('admin.ppdb.periods.close', $period) }}">@csrf<button class="text-xs font-semibold text-amber-700">Tutup</button></form>@endif</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-600">Belum ada periode PPDB.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($periods->hasPages())<div class="border-t border-slate-100 px-4 py-4">{{ $periods->links() }}</div>@endif
        </div>
    </div>
@endsection

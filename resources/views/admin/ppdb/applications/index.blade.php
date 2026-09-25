@extends('layouts.app')

@section('title', 'Pengajuan PPDB · Admin')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">PPDB</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Antrean pengajuan</h1>
                <p class="mt-2 text-sm text-slate-600">Data sensitif hanya tampil kepada administrator dan reviewer yang ditugaskan.</p>
            </div>
            <a href="{{ route('admin.ppdb.periods.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Kelola periode</a>
        </header>

        <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4">
            <label class="grid gap-1 text-sm font-medium text-slate-700">Status<select name="status" class="rounded-lg border-slate-300 text-sm"><option value="">Semua</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-sm font-medium text-slate-700">ID periode<input type="number" name="period" value="{{ request('period') }}" class="rounded-lg border-slate-300 text-sm"></label>
            <label class="grid gap-1 text-sm font-medium text-slate-700">ID reviewer<input type="number" name="reviewer" value="{{ request('reviewer') }}" class="rounded-lg border-slate-300 text-sm"></label>
            <button class="self-end rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Referensi</th><th class="px-4 py-3">Periode</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Reviewer</th><th class="px-4 py-3">Dikirim</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse($applications as $application)<tr><td class="px-4 py-3"><a class="font-mono font-semibold text-cyan-700 hover:text-cyan-900" href="{{ route('admin.ppdb.applications.show', $application) }}">{{ $application->registration_number }}</a>@if($application->duplicate_flag)<span class="ml-2 rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800">Duplikat perlu ditinjau</span>@endif</td><td class="px-4 py-3 text-slate-600">{{ $application->period?->name }}</td><td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $application->status->label() }}</span></td><td class="px-4 py-3 text-slate-600">{{ $application->reviewer?->name ?? 'Belum ditugaskan' }}</td><td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $application->submitted_at?->translatedFormat('d M Y H:i') }}</td></tr>@empty<tr><td colspan="5" class="px-4 py-8 text-center text-slate-600">Belum ada pengajuan.</td></tr>@endforelse
            </tbody></table></div>
            @if($applications->hasPages())<div class="border-t border-slate-100 px-4 py-4">{{ $applications->links() }}</div>@endif
        </div>
    </div>
@endsection

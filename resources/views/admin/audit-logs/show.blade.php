@extends('layouts.app')

@section('title', 'Detail audit log · Sekolah Kita')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <a href="{{ route('admin.audit-logs.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">← Kembali ke audit log</a>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">{{ $auditLog->action }}</p>
            <h1 class="mt-2 text-2xl font-bold text-slate-950">Detail audit event</h1>
            <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Waktu</dt><dd class="mt-1 text-sm text-slate-900">{{ $auditLog->occurred_at?->translatedFormat('d M Y H:i:s') }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Aktor</dt><dd class="mt-1 text-sm text-slate-900">{{ $auditLog->actor?->name ?? 'Sistem' }}</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Target</dt><dd class="mt-1 text-sm text-slate-900">{{ $auditLog->target_type }}@if($auditLog->target_id) #{{ $auditLog->target_id }}@endif</dd></div>
                <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hasil</dt><dd class="mt-1 text-sm text-slate-900">{{ $auditLog->result }}</dd></div>
            </dl>
            <div class="mt-6 border-t border-slate-100 pt-5">
                <h2 class="text-sm font-semibold text-slate-950">Metadata yang direduksi</h2>
                <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">{{ json_encode($auditLog->changed_fields ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </section>
    </div>
@endsection

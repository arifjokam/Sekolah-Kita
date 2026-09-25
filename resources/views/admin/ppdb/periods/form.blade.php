@extends('layouts.app')

@section('title', ($admissionPeriod ? 'Edit' : 'Tambah').' periode PPDB')

@section('content')
    <div class="mx-auto max-w-4xl space-y-6">
        <a href="{{ route('admin.ppdb.periods.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">← Kembali ke periode PPDB</a>
        <header>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Administrasi PPDB</p>
            <h1 class="mt-2 text-3xl font-bold text-slate-950">{{ $admissionPeriod ? 'Edit periode' : 'Tambah periode' }}</h1>
        </header>

        <x-form.error-summary />
        <form method="POST" action="{{ $admissionPeriod ? route('admin.ppdb.periods.update', $admissionPeriod) : route('admin.ppdb.periods.store') }}" class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            @csrf
            @if($admissionPeriod) @method('PUT') @endif
            <div class="grid gap-5 sm:grid-cols-2">
                <label class="grid gap-1 text-sm font-medium text-slate-700">Tahun ajaran<select name="academic_year_id" required class="rounded-lg border-slate-300">@foreach($academicYears as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id', $admissionPeriod?->academic_year_id) === $year->id)>{{ $year->label }} ({{ $year->code }})</option>@endforeach</select><x-form.field-error name="academic_year_id" /></label>
                <label class="grid gap-1 text-sm font-medium text-slate-700">Nama periode<input name="name" value="{{ old('name', $admissionPeriod?->name) }}" required class="rounded-lg border-slate-300"><x-form.field-error name="name" /></label>
                <label class="grid gap-1 text-sm font-medium text-slate-700">Slug<input name="slug" value="{{ old('slug', $admissionPeriod?->slug) }}" required class="rounded-lg border-slate-300"><x-form.field-error name="slug" /></label>
                <label class="grid gap-1 text-sm font-medium text-slate-700">Kuota total (opsional)<input type="number" name="quota" value="{{ old('quota', $admissionPeriod?->quota) }}" min="1" class="rounded-lg border-slate-300"><x-form.field-error name="quota" /></label>
                <label class="grid gap-1 text-sm font-medium text-slate-700">Dibuka<input type="datetime-local" name="opens_at" value="{{ old('opens_at', $admissionPeriod?->opens_at?->format('Y-m-d\TH:i')) }}" required class="rounded-lg border-slate-300"><x-form.field-error name="opens_at" /></label>
                <label class="grid gap-1 text-sm font-medium text-slate-700">Ditutup<input type="datetime-local" name="closes_at" value="{{ old('closes_at', $admissionPeriod?->closes_at?->format('Y-m-d\TH:i')) }}" required class="rounded-lg border-slate-300"><x-form.field-error name="closes_at" /></label>
                <label class="grid gap-1 text-sm font-medium text-slate-700 sm:col-span-2">Deskripsi publik<textarea name="description" rows="4" class="rounded-lg border-slate-300">{{ old('description', $admissionPeriod?->description) }}</textarea><x-form.field-error name="description" /></label>
                <label class="grid gap-1 text-sm font-medium text-slate-700 sm:col-span-2">Instruksi publik<textarea name="instructions" rows="5" class="rounded-lg border-slate-300">{{ old('instructions', $admissionPeriod?->instructions) }}</textarea><x-form.field-error name="instructions" /></label>
                <label class="grid gap-1 text-sm font-medium text-slate-700 sm:col-span-2">Catatan internal<textarea name="internal_notes" rows="3" class="rounded-lg border-slate-300">{{ old('internal_notes', $admissionPeriod?->internal_notes) }}</textarea><x-form.field-error name="internal_notes" /></label>
            </div>
            <div class="flex justify-end"><button type="submit" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Simpan periode</button></div>
        </form>
    </div>
@endsection

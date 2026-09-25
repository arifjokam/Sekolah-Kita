@extends('layouts.public')

@section('title', 'Status PPDB · Sekolah Kita')

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
        <a href="{{ route('ppdb.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">← Informasi PPDB</a>
        <header class="mt-6">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-cyan-700">Halaman status pribadi</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Status pengajuan</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Nomor referensi: <strong class="font-mono text-slate-900">{{ $status['reference'] }}</strong></p>
        </header>

        <section class="mt-8 rounded-3xl border border-cyan-100 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="status-heading">
            <p class="text-sm text-slate-500">Status saat ini</p>
            <h2 id="status-heading" class="mt-2 text-2xl font-bold text-cyan-800">{{ $status['status_label'] }}</h2>
            @if ($status['public_message'])
                <p class="mt-4 text-sm leading-6 text-slate-700">{{ $status['public_message'] }}</p>
            @endif
            @if ($status['revision_deadline'])
                <p class="mt-3 text-sm font-medium text-amber-800">Batas revisi: {{ $status['revision_deadline'] }}</p>
            @endif
        </section>

        @if ($status['can_revise'])
            <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="revision-heading">
                <h2 id="revision-heading" class="text-lg font-semibold text-slate-950">Kirim revisi</h2>
                <p class="mt-1 text-sm text-slate-600">Perbarui data yang diminta. Perubahan akan dikirim untuk ditinjau kembali.</p>
                <form method="POST" action="{{ route('ppdb.status.correct', $statusToken) }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="access_token" value="{{ $revisionToken }}">
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Nama lengkap<input name="applicant_name" value="{{ old('applicant_name') }}" required class="rounded-lg border-slate-300"></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Email<input type="email" name="contact_email" value="{{ old('contact_email') }}" required class="rounded-lg border-slate-300"></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Telepon<input name="contact_phone" value="{{ old('contact_phone') }}" required class="rounded-lg border-slate-300"></label>
                    <label class="grid gap-1 text-sm font-medium text-slate-700">Alamat<textarea name="address" rows="3" class="rounded-lg border-slate-300">{{ old('address') }}</textarea></label>
                    <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-slate-300 text-cyan-700"> Saya menyetujui data yang diperbarui.</label>
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Kirim revisi</button>
                </form>
            </section>

            <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="document-heading">
                <h2 id="document-heading" class="text-lg font-semibold text-slate-950">Unggah dokumen</h2>
                <p class="mt-1 text-sm text-slate-600">Dokumen disimpan privat dan hanya dapat diunduh reviewer yang berwenang.</p>
                @if ($documentToken)
                    <form method="POST" action="{{ route('ppdb.status.document.store', $statusToken) }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="access_token" value="{{ $documentToken }}">
                        <label class="grid gap-1 text-sm font-medium text-slate-700">Jenis dokumen<select name="document_type" class="rounded-lg border-slate-300"><option value="identity">Dokumen identitas</option><option value="birth_certificate">Akta kelahiran</option><option value="previous_school_report">Rapor sekolah sebelumnya</option><option value="other">Dokumen pendukung</option></select></label>
                        <label class="grid gap-1 text-sm font-medium text-slate-700">Berkas<input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border-slate-300"></label>
                        <button type="submit" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Kirim dokumen</button>
                    </form>
                @endif
            </section>
        @endif

        @if (! $status['can_revise'] && ! in_array($status['status'], ['withdrawn', 'expired'], true))
            <form method="POST" action="{{ route('ppdb.status.withdraw', $statusToken) }}" class="mt-8">@csrf<input type="hidden" name="access_token" value="{{ $withdrawalToken }}"><button type="submit" class="text-sm font-semibold text-rose-700 hover:text-rose-900">Hentikan pengajuan</button></form>
        @endif
    </div>
@endsection

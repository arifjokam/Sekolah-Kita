@extends('layouts.app')

@section('title', 'Dokumen pendukung · PPDB')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard pendaftar', 'route' => 'ppdb.portal.dashboard'],
            ['label' => 'Dokumen pendukung'],
        ];

        $statusClasses = [
            'pending' => 'bg-amber-100 text-amber-800',
            'verified' => 'bg-emerald-100 text-emerald-800',
            'needs_revision' => 'bg-amber-100 text-amber-800',
            'rejected' => 'bg-rose-100 text-rose-800',
            'superseded' => 'bg-slate-200 text-slate-700',
            'expired' => 'bg-slate-200 text-slate-700',
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Pendaftaran"
        title="Dokumen pendukung"
        description="Unggah satu berkas per kategori. Berkas disimpan secara privat dan hanya dapat diunduh Anda sendiri dan reviewer yang ditugaskan."
    >
        <x-slot:actions>
            <a href="{{ route('ppdb.portal.review') }}" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Review data</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-form.error-summary />

    @unless ($canUpload)
        <div class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm leading-6 text-slate-700" role="status">
            Dokumen hanya dapat diunggah atau diunggah ulang saat pengajuan masih disusun atau menunggu perbaikan dari
            administrator. Status dokumen yang sudah ditinjau tidak dapat diubah.
        </div>
    @endunless

    <ul class="grid gap-4 lg:grid-cols-2">
        @foreach ($slots as $slot)
            @php
                /** @var \App\Enums\AdmissionDocumentType $type */
                $type = $slot['type'];
                $document = $slot['document'];
                $needsReplacement = $document?->needsReplacement() ?? false;
                $replaceable = $canUpload && ($document === null
                    || in_array($document->verification_status->value, ['pending', 'needs_revision'], true));
            @endphp
            <li class="flex flex-col rounded-2xl border bg-white p-5 shadow-sm {{ $slot['required'] && $document === null ? 'border-amber-300' : 'border-slate-200' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-slate-950">
                            {{ $type->label() }}
                            @if ($slot['required'])
                                <span class="ml-1 text-xs font-semibold uppercase tracking-wide text-rose-700">wajib</span>
                            @endif
                        </h2>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $type->hint() }}</p>
                    </div>
                    @if ($document)
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$document->verification_status->value] ?? 'bg-slate-200 text-slate-700' }}">
                            {{ $document->verification_status->label() }}
                        </span>
                    @else
                        <span class="shrink-0 rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">
                            Belum diunggah
                        </span>
                    @endif
                </div>

                @if ($document)
                    <dl class="mt-4 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
                        <div><dt class="font-semibold uppercase tracking-wide text-slate-500">Versi</dt><dd class="mt-0.5 text-slate-900">v{{ $document->version }}</dd></div>
                        <div><dt class="font-semibold uppercase tracking-wide text-slate-500">Diunggah</dt><dd class="mt-0.5 text-slate-900">{{ $document->created_at?->translatedFormat('d M Y H:i') }}</dd></div>
                        <div class="sm:col-span-2">
                            <dt class="font-semibold uppercase tracking-wide text-slate-500">Berkas</dt>
                            <dd class="mt-0.5">
                                <a href="{{ route('ppdb.portal.documents.download', $document) }}"
                                   class="font-semibold text-cyan-700 hover:text-cyan-900">Unduh berkas</a>
                            </dd>
                        </div>
                        @if ($document->mediaAsset)
                            <div class="sm:col-span-2">
                                <dt class="font-semibold uppercase tracking-wide text-slate-500">Pemeriksaan berkas</dt>
                                <dd class="mt-0.5 text-slate-900">{{ $document->mediaAsset->scan_status->label() }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($document->reviewer_note)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                            <p class="font-semibold">Catatan reviewer</p>
                            <p class="mt-1 leading-6">{{ $document->reviewer_note }}</p>
                        </div>
                    @endif

                    @if ($needsReplacement)
                        <p class="mt-4 text-sm font-semibold text-amber-800">Dokumen ini perlu diunggah ulang dengan perbaikan yang diminta.</p>
                    @endif
                @endif

                @if ($replaceable)
                    <form
                        method="POST"
                        action="{{ $document
                            ? route('ppdb.portal.documents.replace', $document)
                            : route('ppdb.portal.documents.store', $application) }}"
                        enctype="multipart/form-data"
                        class="mt-5 space-y-3 border-t border-slate-100 pt-4"
                    >
                        @csrf
                        @if ($document) @method('PUT') @endif

                        @unless ($document)
                            <input type="hidden" name="document_type" value="{{ $type->value }}">
                        @endunless

                        <label class="grid gap-1 text-sm font-medium text-slate-700">
                            <span>{{ $document ? 'Ganti dengan berkas yang lebih baik' : 'Unggah '.$type->label() }}</span>
                            <input
                                type="file"
                                name="document"
                                required
                                accept=".pdf,.jpg,.jpeg,.png"
                                class="mt-1 rounded-xl border border-slate-300 text-sm file:mr-3 file:rounded-l-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:text-sm file:font-semibold"
                            >
                        </label>

                        <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                            {{ $document ? 'Unggah ulang' : 'Unggah' }}
                        </button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>

    <p class="mt-6 text-sm text-slate-600">
        Format yang diterima: PDF, JPG, atau PNG. Ukuran maksimum
        {{ number_format(((int) config('sekolah-kita.ppdb.max_document_bytes')) / 1048576, 1) }} MB per berkas.
    </p>
@endsection

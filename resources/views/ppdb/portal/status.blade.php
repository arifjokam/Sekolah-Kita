@extends('layouts.app')

@section('title', 'Status pendaftaran · PPDB')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard pendaftar', 'route' => 'ppdb.portal.dashboard'],
            ['label' => 'Status pendaftaran'],
        ];

        $statusHeadline = match (true) {
            $application->isAccepted() => 'Pengajuan Anda diterima.',
            $application->status === \App\Enums\AdmissionApplicationStatus::Rejected => 'Pengajuan Anda belum dapat diterima.',
            $application->canRevise() => 'Administrator meminta perbaikan.',
            $application->status === \App\Enums\AdmissionApplicationStatus::UnderReview => 'Pengajuan sedang ditinjau.',
            $application->isSubmitted() => 'Pengajuan sedang menunggu peninjauan.',
            $application->isDraft() => 'Pengajuan masih disusun.',
            default => 'Pengajuan selesai diproses.',
        };
    @endphp

    <x-admin.page-header
        eyebrow="PPDB"
        title="Status pendaftaran"
        :description="'Nomor pendaftaran '.$application->registration_number.' · Periode '.($period?->name ?? '—').'. Halaman ini hanya menampilkan pengajuan Anda sendiri.'"
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.stat-card label="Status" :value="$applicant_status['label']">
            <x-candidate.status-chip class="mt-3" size="lg" :value="$applicant_status['tone']" :label="$applicant_status['label']" />
        </x-admin.stat-card>
        <x-admin.stat-card label="Diajukan" :value="$application->submitted_at?->translatedFormat('d M Y') ?? 'Belum diajukan'" :hint="$application->submitted_at?->translatedFormat('H:i')" />
        <x-admin.stat-card label="Kelengkapan" :value="$percentage.'%'" :tone="$percentage === 100 ? 'muted' : 'warning'" />
        <x-admin.stat-card label="Kontak WhatsApp" :value="$application->contact_whatsapp ?? $application->contact_phone ?? '—'" :hint="$whatsapp['configured'] ? 'Notifikasi WhatsApp aktif' : 'Notifikasi WhatsApp belum dikonfigurasi'" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <section aria-labelledby="history-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="history-heading" class="text-lg font-bold text-slate-950">Riwayat status</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $statusHeadline }}</p>

            <ol class="mt-5 space-y-5">
                @foreach ($history as $entry)
                    <li class="border-l-2 border-cyan-200 pl-4">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ $entry->to_status->label() }}
                            @if ($entry->from_status)
                                <span class="font-normal text-slate-500">dari {{ $entry->from_status->label() }}</span>
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $entry->occurred_at?->translatedFormat('d M Y H:i') }} · {{ $entry->actor_type->label() }}
                        </p>
                        @if ($entry->public_message)
                            <p class="mt-1 text-sm leading-6 text-slate-700">{{ $entry->public_message }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="space-y-6">
            @if ($missing !== [] && $can_edit)
                <section aria-labelledby="missing-heading" class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <h2 id="missing-heading" class="text-lg font-bold text-amber-950">Yang masih perlu dilengkapi</h2>
                    <ul class="mt-3 space-y-1 text-sm text-amber-900">
                        @foreach ($missing as $item)
                            <li class="flex gap-2">
                                <span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-amber-600" aria-hidden="true"></span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($documents->contains(fn ($document) => $document->needsReplacement()))
                <section aria-labelledby="revision-heading" class="rounded-2xl border border-amber-300 bg-amber-50 p-5">
                    <h2 id="revision-heading" class="text-lg font-bold text-amber-950">Dokumen perlu diperbaiki</h2>
                    <ul class="mt-3 space-y-3 text-sm text-amber-900">
                        @foreach ($documents->filter(fn ($document) => $document->needsReplacement()) as $document)
                            <li>
                                <p class="font-semibold">{{ $document->document_type->label() }}</p>
                                @if ($document->reviewer_note)
                                    <p class="mt-1 leading-6">{{ $document->reviewer_note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('ppdb.portal.documents') }}" class="mt-4 inline-flex rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-600">Perbaiki dokumen</a>
                </section>
            @endif

            <section aria-labelledby="whatsapp-heading" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="whatsapp-heading" class="text-lg font-bold text-slate-950">Pemberitahuan WhatsApp</h2>
                @if ($whatsapp['configured'])
                    <p class="mt-2 text-sm leading-6 text-slate-700">
                        Pemberitahuan dikirim ke {{ $whatsapp['number'] ?? 'nomor pemohon' }} melalui driver
                        {{ $whatsapp['driver'] }}. Pesan yang gagal terkirim tercatat di halaman notifikasi.
                    </p>
                @else
                    <p class="mt-2 text-sm leading-6 text-slate-700">
                        Provider WhatsApp belum dikonfigurasi di sekolah ini, sehingga tidak ada pesan WhatsApp yang
                        dikirim. Semua pemberitahuan tetap muncul di dalam aplikasi dan surel.
                    </p>
                @endif
                <a href="{{ route('ppdb.portal.notifications') }}" class="mt-3 inline-block text-sm font-semibold text-cyan-700 hover:text-cyan-900">Lihat riwayat notifikasi</a>
            </section>
        </div>
    </div>
@endsection

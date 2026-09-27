@extends('layouts.app')

@section('title', 'Pengajuan '.$admissionApplication->registration_number.' · Admin')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <a href="{{ route('admin.ppdb.applications.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">← Kembali ke antrean</a>
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Review pengajuan</p>
                <h1 class="mt-2 font-mono text-3xl font-bold text-slate-950">{{ $admissionApplication->registration_number }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ $admissionApplication->period?->name }} · dikirim {{ $admissionApplication->submitted_at?->translatedFormat('d M Y H:i') }}</p>
            </div>
            <span class="w-fit rounded-full bg-cyan-100 px-3 py-1.5 text-sm font-semibold text-cyan-800">{{ $admissionApplication->status->label() }}</span>
        </header>

        @if($admissionApplication->duplicate_flag)<div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Penanda duplikat perlu ditinjau administrator. Jangan tampilkan alasan pencocokan pada respons publik.</div>@endif

        @if ($admissionApplication->isPortalApplication())
            <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900">
                <p class="font-semibold">Pengajuan melalui portal pendaftar</p>
                <p class="mt-1">
                    Pendaftar memiliki akun
                    @if ($admissionApplication->applicantUser)
                        <strong>{{ $admissionApplication->applicantUser->email }}</strong>
                        dan dapat memantau statusnya sendiri
                    @else
                        <strong>yang sudah tidak aktif</strong> dan tidak dapat memantau statusnya sendiri
                    @endif
                    @if ($admissionApplication->contact_whatsapp)
                        · WhatsApp <strong>{{ $admissionApplication->contact_whatsapp }}</strong>
                    @endif
                </p>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
            @if($canReview)
            <div class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="applicant-heading">
                    <h2 id="applicant-heading" class="text-lg font-semibold text-slate-950">Data pemohon</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Nama</dt><dd class="mt-1 text-sm text-slate-900">{{ $admissionApplication->applicant_name }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kontak</dt><dd class="mt-1 text-sm text-slate-900">{{ $admissionApplication->contact_email }}<br>{{ $admissionApplication->contact_phone }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tanggal lahir</dt><dd class="mt-1 text-sm text-slate-900">{{ $admissionApplication->birth_date?->format('d M Y') ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Asal sekolah</dt><dd class="mt-1 text-sm text-slate-900">{{ $admissionApplication->current_school ?? '—' }}</dd></div>
                    </dl>
                    @if($admissionApplication->address)<p class="mt-4 text-sm leading-6 text-slate-700">{{ $admissionApplication->address }}</p>@endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="guardian-heading">
                    <h2 id="guardian-heading" class="text-lg font-semibold text-slate-950">Wali</h2>
                    <div class="mt-4 space-y-3">@forelse($admissionApplication->guardians as $guardian)<div class="rounded-xl border border-slate-200 p-3 text-sm"><p class="font-semibold text-slate-900">{{ $guardian->legal_name }} <span class="font-normal text-slate-500">({{ $guardian->relationship_type->label() }})</span></p><p class="mt-1 text-slate-600">{{ $guardian->phone }} @if($guardian->is_primary_contact) · Kontak utama @endif</p></div>@empty<p class="text-sm text-slate-600">Data wali belum tersedia.</p>@endforelse</div>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="document-heading">
                    <div class="flex items-center justify-between gap-4">
                        <h2 id="document-heading" class="text-lg font-semibold text-slate-950">Dokumen</h2>
                        <span class="text-xs text-slate-500">Private · no-store</span>
                    </div>

                    @if ($admissionApplication->hasOpenDocumentRevision())
                        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status">
                            Ada dokumen yang dikembalikan untuk diperbaiki. Pendaftar dapat mengunggah ulang
                            dokumen tersebut saja; dokumen lain yang sudah diterima tidak berubah.
                        </p>
                    @endif

                    <div class="mt-4 space-y-3">
                        @forelse ($admissionApplication->documents->sortByDesc('id') as $document)
                            <div class="rounded-xl border border-slate-200 p-3">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">
                                            {{ $document->document_type->label() }}
                                            <span class="font-normal text-slate-500">v{{ $document->version }}</span>
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ $document->verification_status->label() }} ·
                                            {{ $document->mediaAsset?->scan_status?->label() ?? 'Memeriksa' }}
                                            @if ($document->reviewed_at)
                                                · ditinjau {{ $document->reviewed_at->translatedFormat('d M Y') }}
                                            @endif
                                        </p>
                                    </div>

                                    <a href="{{ route('admin.ppdb.documents.download', $document) }}"
                                       class="shrink-0 text-xs font-semibold text-cyan-700 hover:text-cyan-900">
                                        Unduh
                                    </a>
                                </div>

                                @if ($document->reviewer_note)
                                    <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                                        <span class="font-semibold text-slate-900">Catatan reviewer:</span>
                                        {{ $document->reviewer_note }}
                                    </p>
                                @endif

                                @if ($document->reviews->isNotEmpty())
                                    <details class="mt-3">
                                        <summary class="cursor-pointer text-xs font-semibold text-slate-600">
                                            Riwayat review dokumen ({{ $document->reviews->count() }})
                                        </summary>
                                        <ol class="mt-2 space-y-2 border-l-2 border-slate-200 pl-3">
                                            @foreach ($document->reviews->sortByDesc('id') as $review)
                                                <li class="text-xs text-slate-600">
                                                    <span class="font-semibold text-slate-800">{{ $review->eventLabel() }}</span>
                                                    · {{ $review->reviewed_at?->translatedFormat('d M Y H:i') }}
                                                    @if ($review->reviewer)
                                                        · {{ $review->reviewer->name }}
                                                    @endif
                                                    @if ($review->internal_note)
                                                        <span class="block text-slate-500">{{ $review->internal_note }}</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ol>
                                    </details>
                                @endif

                                @if ($canReview && $document->verification_status->isSupplied())
                                    <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                                        @foreach (\App\Enums\AdmissionDocumentVerificationStatus::reviewerOptions() as $value => $label)
                                            @if ($value === \App\Enums\AdmissionDocumentVerificationStatus::NeedsRevision->value)
                                                <button type="button"
                                                        class="rounded-lg border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-50"
                                                        data-document-revision
                                                        data-target="revision-note-{{ $document->getKey() }}">
                                                    {{ $label }}
                                                </button>
                                            @else
                                                <form method="POST" action="{{ route('admin.ppdb.documents.update-status', $document) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="{{ $value }}">
                                                    <button class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $value === 'verified' ? 'bg-emerald-700 text-white hover:bg-emerald-800' : 'border border-rose-300 text-rose-700 hover:bg-rose-50' }}">
                                                        {{ $label }}
                                                    </button>
                                                </form>
                                            @endif
                                        @endforeach
                                    </div>

                                    {{-- The reason is mandatory when a document is sent back, and it is the only
                                         thing the applicant sees, so it is a real field rather than a
                                         prompt-free button. --}}
                                    <form id="revision-note-{{ $document->getKey() }}"
                                          method="POST"
                                          action="{{ route('admin.ppdb.documents.request-revision', $document) }}"
                                          class="mt-3 hidden gap-3 rounded-xl bg-amber-50 p-3">
                                        @csrf
                                        @method('PATCH')
                                        <label class="grid gap-1 text-sm font-medium text-amber-900">
                                            Alasan dokumen dikembalikan
                                            <textarea name="reviewer_note" rows="2" maxlength="500" required
                                                      class="rounded-lg border-amber-300 text-sm"
                                                      placeholder="Contoh: foto tidak terbaca, silakan unggah ulang dengan pencahayaan lebih terang."></textarea>
                                        </label>
                                        <button class="w-fit rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">
                                            Kirim permintaan perbaikan
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-slate-600">Belum ada dokumen.</p>
                        @endforelse
                    </div>

                    @if ($admissionApplication->status->value === 'under_review' && $canReview)
                        <form method="POST"
                              action="{{ route('admin.ppdb.applications.documents.store', $admissionApplication) }}"
                              enctype="multipart/form-data"
                              class="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                            @csrf
                            <label class="grid gap-1 text-sm font-medium text-slate-700">
                                Jenis
                                <select name="document_type" class="rounded-lg border-slate-300 text-sm">
                                    @foreach (\App\Enums\AdmissionDocumentType::cases() as $type)
                                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="grid gap-1 text-sm font-medium text-slate-700">
                                Berkas
                                <input type="file" name="document" required accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border-slate-300 text-sm">
                            </label>
                            <button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Unggah</button>
                        </form>
                    @else
                        <p class="mt-5 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">Dokumen hanya dapat diunggah selama pengajuan berstatus ditinjau.</p>
                    @endif
                </section>
            </div>
            @else
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">Data pemohon dan dokumen hanya tersedia bagi reviewer yang ditugaskan.</div>
            @endif

            <div class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="workflow-heading">
                    <h2 id="workflow-heading" class="text-lg font-semibold text-slate-950">Aksi reviewer</h2>
                    @if($admissionApplication->assigned_reviewer_user_id === null)<form method="POST" action="{{ route('admin.ppdb.applications.reviewer.assign', $admissionApplication) }}" class="mt-4 space-y-3">@csrf<label class="grid gap-1 text-sm font-medium text-slate-700">Tugaskan reviewer<select name="reviewer_user_id" class="rounded-lg border-slate-300">@foreach($reviewers as $reviewer)<option value="{{ $reviewer->id }}">{{ $reviewer->name }}</option>@endforeach</select></label><button class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white">Tugaskan</button></form>@else<p class="mt-3 text-sm text-slate-600">Reviewer: <strong class="text-slate-900">{{ $admissionApplication->reviewer?->name }}</strong></p>@endif
                    @if($canReview && $admissionApplication->status->value === 'submitted' && $admissionApplication->assigned_reviewer_user_id)<form method="POST" action="{{ route('admin.ppdb.applications.start-review', $admissionApplication) }}" class="mt-4">@csrf<button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Mulai review</button></form>@endif
                    @if($canReview && $admissionApplication->status->value === 'under_review')<div class="mt-5 space-y-4"><form method="POST" action="{{ route('admin.ppdb.applications.request-revision', $admissionApplication) }}" class="space-y-3">@csrf<label class="grid gap-1 text-sm font-medium text-slate-700">Pesan revisi<textarea name="public_message" required maxlength="255" class="rounded-lg border-slate-300"></textarea></label><label class="grid gap-1 text-sm font-medium text-slate-700">Batas revisi<input type="datetime-local" name="deadline" class="rounded-lg border-slate-300"></label><button class="rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-800">Minta revisi</button></form><form method="POST" action="{{ route('admin.ppdb.applications.decide', $admissionApplication) }}" class="space-y-3 border-t border-slate-100 pt-4">@csrf<label class="grid gap-1 text-sm font-medium text-slate-700">Keputusan<select name="decision" class="rounded-lg border-slate-300"><option value="accept">Terima</option><option value="waitlist">Daftar tunggu</option><option value="reject">Tolak</option></select></label><label class="grid gap-1 text-sm font-medium text-slate-700">Kelas tujuan (untuk diterima)<select name="target_class_id" class="rounded-lg border-slate-300"><option value="">Pilih kelas</option>@foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->grade_level }} {{ $class->name }} ({{ $class->status->label() }})</option>@endforeach</select></label><label class="grid gap-1 text-sm font-medium text-slate-700">Catatan internal<textarea name="internal_note" class="rounded-lg border-slate-300"></textarea></label><button class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white">Simpan keputusan</button></form></div>@endif
                    @if($canReview && $admissionApplication->status->value === 'accepted' && $admissionApplication->conversion_status->value !== 'completed')<form method="POST" action="{{ route('admin.ppdb.applications.convert', $admissionApplication) }}" class="mt-5">@csrf<button class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">Konversi ke siswa</button></form>@endif
                    @if($admissionApplication->conversion_status->value === 'completed')<p class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">Konversi selesai. Data siswa dan enrollment sudah dibuat.</p>@endif
                </section>

                @if ($canReview)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="announce-heading">
                    <h2 id="announce-heading" class="text-lg font-semibold text-slate-950">Kirim pengumuman</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Dikirim ke dalam aplikasi untuk pendaftar yang punya akun, dan ke nomor WhatsApp
                        pada pengajuan ini.
                    </p>

                    @unless ($whatsapp['configured'])
                        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status">
                            Driver WhatsApp belum dikonfigurasi, jadi hanya notifikasi dalam aplikasi yang
                            terkirim. Hasil percobaan WhatsApp dicatat sebagai
                            <strong>belum dikonfigurasi</strong>, bukan sebagai terkirim.
                        </p>
                    @endunless

                    <form method="POST" action="{{ route('admin.ppdb.applications.announce', $admissionApplication) }}" class="mt-4 space-y-3">
                        @csrf
                        <label class="grid gap-1 text-sm font-medium text-slate-700">
                            Judul
                            <input type="text" name="title" required maxlength="120" value="Informasi PPDB" class="rounded-lg border-slate-300">
                        </label>
                        <label class="grid gap-1 text-sm font-medium text-slate-700">
                            Isi pengumuman
                            <textarea name="message" required minlength="5" maxlength="1000" rows="3" class="rounded-lg border-slate-300" placeholder="Contoh: Berkas Anda sedang ditinjau. Hubungi wali kelas bila ada keterangan."></textarea>
                        </label>
                        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Kirim pengumuman</button>
                    </form>

                    @if ($canShowDeliveries)
                        @if ($deliveries->isNotEmpty())
                            <details class="mt-4">
                                <summary class="cursor-pointer text-xs font-semibold text-slate-600">Riwayat pengiriman ({{ $deliveries->count() }})</summary>
                                <ul class="mt-2 space-y-2">
                                    @foreach ($deliveries as $delivery)
                                        <li class="text-xs text-slate-600">
                                            <span class="font-semibold text-slate-800">{{ $delivery->status->label() }}</span>
                                            · {{ $delivery->recipient ?? 'tanpa nomor' }}
                                            · {{ $delivery->created_at?->translatedFormat('d M Y H:i') }}
                                            @if ($delivery->last_error)
                                                <span class="block text-amber-700">{{ $delivery->last_error }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <p class="mt-4 text-xs text-slate-500">Belum ada percobaan pengiriman untuk pengajuan ini.</p>
                        @endif
                    @else
                        <p class="mt-4 text-xs text-slate-500">
                            Pengajuan ini berasal dari formulir publik, sehingga tidak punya akun
                            pendaftar. Riwayat pengiriman tidak dapat dikaitkan ke satu pengajuan;
                            periksa log aplikasi untuk hasil pengirimannya.
                        </p>
                    @endif
                </section>
                @endif

                @if($canReview)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="timeline-heading"><h2 id="timeline-heading" class="text-lg font-semibold text-slate-950">Riwayat status</h2><ol class="mt-4 space-y-4">@foreach($admissionApplication->statusHistories->sortByDesc('workflow_version') as $history)<li class="border-l-2 border-cyan-200 pl-4"><p class="text-sm font-semibold text-slate-900">{{ $history->to_status->label() }}</p><p class="mt-1 text-xs text-slate-500">{{ $history->occurred_at?->translatedFormat('d M Y H:i') }} · {{ $history->actor_type->label() }}</p>@if($history->public_message)<p class="mt-1 text-sm text-slate-600">{{ $history->public_message }}</p>@endif</li>@endforeach</ol></section>
                @endif
            </div>
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Review pendaftaran · PPDB')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard pendaftar', 'route' => 'ppdb.portal.dashboard'],
            ['label' => 'Review data'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Pendaftaran"
        title="Review data"
        description="Periksa sekali lagi sebelum mengajukan. Pengajuan hanya bisa diajukan bila semua bagian wajib sudah lengkap."
    />

    <x-form.error-summary />

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <x-candidate.completeness :sections="$sections" :percentage="$percentage" />

            <section aria-labelledby="biodata-review" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="biodata-review" class="text-lg font-bold text-slate-950">Biodata siswa</h2>
                    @if ($can_edit)
                        <a href="{{ route('ppdb.portal.biodata') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Ubah</a>
                    @endif
                </div>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($biodata as $row)
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $row['label'] }}</dt>
                            <dd class="mt-1 text-sm text-slate-900">{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section aria-labelledby="guardian-review" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="guardian-review" class="text-lg font-bold text-slate-950">Orang tua / wali</h2>
                    @if ($can_edit)
                        <a href="{{ route('ppdb.portal.guardians') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Ubah</a>
                    @endif
                </div>
                <ul class="mt-4 space-y-3">
                    @forelse ($guardianRows as $guardian)
                        <li class="rounded-xl border border-slate-200 p-4 text-sm">
                            <p class="font-semibold text-slate-900">
                                {{ $guardian->legal_name }}
                                <span class="font-normal text-slate-500">({{ $guardian->relationship_type->label() }})</span>
                                @if ($guardian->is_primary_contact)
                                    <span class="ml-2 rounded-full bg-cyan-100 px-2 py-0.5 text-xs font-semibold text-cyan-800">Kontak utama</span>
                                @endif
                            </p>
                            <p class="mt-1 text-slate-600">
                                {{ $guardian->phone ?? '—' }}
                                @if ($guardian->whatsapp_number) · WhatsApp {{ $guardian->whatsapp_number }} @endif
                                @if ($guardian->occupation) · {{ $guardian->occupation }} @endif
                            </p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">Belum ada data wali.</li>
                    @endforelse
                </ul>
            </section>

            <section aria-labelledby="document-review" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="document-review" class="text-lg font-bold text-slate-950">Dokumen</h2>
                    @if ($can_edit)
                        <a href="{{ route('ppdb.portal.documents') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">Ubah</a>
                    @endif
                </div>
                <ul class="mt-4 space-y-3">
                    @forelse ($document_slots as $slot)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4 text-sm">
                            <span class="font-medium text-slate-900">
                                {{ $slot['type']->label() }}
                                @if ($slot['required'])
                                    <span class="ml-1 text-xs font-semibold uppercase tracking-wide text-rose-700">wajib</span>
                                @endif
                            </span>
                            <span class="text-xs font-semibold {{ $slot['document'] ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $slot['document'] ? $slot['document']->verification_status->label() : 'Belum diunggah' }}
                            </span>
                        </li>
                    @empty
                        <li class="text-sm text-slate-600">Belum ada kategori dokumen.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <div class="h-fit space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-bold text-slate-950">Ajukan pendaftaran</h2>

            @if ($missing !== [])
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">
                    <p class="font-semibold">Belum dapat diajukan</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        @foreach (array_slice($missing, 0, 8) as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                    @if (count($missing) > 8)
                        <p class="mt-2">dan {{ count($missing) - 8 }} butir lain.</p>
                    @endif
                </div>
            @else
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                    Semua bagian wajib sudah lengkap.
                </div>
            @endif

            <form method="POST" action="{{ route('ppdb.portal.submit') }}">
                @csrf

                {{--
                    The declaration is the checkbox itself, not a hidden field beside
                    it. A hidden `consent=1` would make the server-side check
                    unfalsifiable — the field would always be present however the
                    form was submitted — so the only honest arrangement is for the
                    value to exist solely when the box is actually ticked.
                --}}
                <label class="flex gap-3 text-sm leading-6 text-slate-700">
                    <input type="checkbox" name="consent" value="1" required
                           class="mt-1 h-4 w-4 rounded border-slate-300 text-cyan-700 focus:ring-cyan-600">
                    <span>
                        Saya menyatakan data di atas benar dan bersedia mengikuti asesmen serta wawancara bila diperlukan.
                    </span>
                </label>

                <button
                    type="submit"
                    @disabled(! $can_submit)
                    class="mt-4 w-full rounded-xl px-5 py-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 {{ $can_submit ? 'bg-cyan-700 text-white hover:bg-cyan-800' : 'cursor-not-allowed bg-slate-300 text-slate-600' }}"
                >
                    Ajukan pendaftaran
                </button>
            </form>

            @unless ($can_submit)
                <p class="text-xs leading-5 text-slate-500">
                    Tombol dinonaktifkan karena pengajuan belum lengkap atau sudah pernah diajukan. Perbaiki bagian yang
                    ditandai lalu kembali ke halaman ini.
                </p>
            @endunless
        </div>
    </div>
@endsection

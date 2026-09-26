@extends('layouts.app')

@section('title', 'Orang tua / wali · PPDB')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard pendaftar', 'route' => 'ppdb.portal.dashboard'],
            ['label' => 'Orang tua / wali'],
        ];

        $editable = $progress['can_edit'];
    @endphp

    <x-admin.page-header
        eyebrow="Pendaftaran"
        title="Orang tua / wali"
        description="Minimal satu wali wajib diisi. Tandai satu wali sebagai kontak utama supaya sekolah tahu whom to call."
    >
        <x-slot:actions>
            <a href="{{ route('ppdb.portal.biodata') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Biodata siswa</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-form.error-summary />

    @unless ($editable)
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900" role="status">
            Data wali tidak dapat diubah pada tahap ini karena pengajuan sudah diajukan atau sedang ditinjau.
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <section aria-labelledby="guardian-list-heading" class="space-y-4">
            <h2 id="guardian-list-heading" class="text-lg font-bold text-slate-950">Wali yang tercatat</h2>

            @forelse ($guardians as $guardian)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-950">{{ $guardian->legal_name }}</p>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ $guardian->relationship_type->label() }}
                                @if ($guardian->occupation) · {{ $guardian->occupation }} @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($guardian->is_primary_contact)
                                <span class="rounded-full bg-cyan-100 px-2.5 py-1 text-xs font-semibold text-cyan-800">Kontak utama</span>
                            @endif
                            @if ($guardian->is_emergency_contact)
                                <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">Kontak darurat</span>
                            @endif
                        </div>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">NIK</dt><dd class="mt-1 text-slate-900">{{ $guardian->national_id ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Telepon</dt><dd class="mt-1 text-slate-900">{{ $guardian->phone ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">WhatsApp</dt><dd class="mt-1 text-slate-900">{{ $guardian->whatsapp_number ?? $guardian->phone ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt><dd class="mt-1 truncate text-slate-900">{{ $guardian->email ?? '—' }}</dd></div>
                    </dl>

                    @if ($guardian->address)
                        <p class="mt-3 text-sm leading-6 text-slate-700">{{ $guardian->address }}</p>
                    @endif

                    @if ($editable)
                        <details class="mt-4 rounded-xl bg-slate-50 p-4">
                            <summary class="cursor-pointer text-sm font-semibold text-cyan-700">Ubah data wali ini</summary>

                            <form method="POST" action="{{ route('ppdb.portal.guardians.update', [$application, $guardian]) }}" class="mt-4 space-y-4">
                                @csrf
                                @method('PUT')
                                @include('ppdb.portal.partials.guardian-fields', ['guardian' => $guardian, 'relationships' => $relationships, 'withSubmit' => true])
                            </form>
                        </details>
                    @endif
                </article>
            @empty
                <x-admin.empty-state
                    label="Belum ada data wali"
                    message="Tambahkan minimal satu orang tua atau wali. Sections ini wajib lengkap sebelum pengajuan dapat diajukan."
                />
            @endforelse
        </section>

        @if ($editable)
            <section aria-labelledby="add-heading" class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 id="add-heading" class="text-lg font-bold text-slate-950">Tambah wali</h2>
                <p class="mt-2 text-sm text-slate-600">Menandai wali baru sebagai kontak utama akan memindahkan status tersebut dari wali lain.</p>

                <form method="POST" action="{{ route('ppdb.portal.guardians.store', $application) }}" class="mt-4 space-y-4">
                    @csrf
                    @include('ppdb.portal.partials.guardian-fields', ['guardian' => null, 'relationships' => $relationships, 'withSubmit' => true])
                </form>
            </section>
        @endif
    </div>
@endsection

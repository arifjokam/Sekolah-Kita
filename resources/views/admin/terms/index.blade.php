@extends('layouts.app')

@section('title', 'Term · Admin')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Akademik'],
            ['label' => 'Term'],
        ];

        $statusOptions = collect($statuses)->mapWithKeys(
            fn ($status) => [$status->value => $status->label()]
        );

        $yearOptions = $academicYears->mapWithKeys(
            fn ($year) => [$year->id => $year->label]
        );

        $filterItems = [
            ['name' => 'search', 'label' => 'Cari', 'placeholder' => 'Label term'],
            ['name' => 'academic_year_id', 'label' => 'Tahun ajaran', 'type' => 'select', 'options' => $yearOptions],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => $statusOptions],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Master data"
        title="Term"
        description="Term membagi satu tahun ajaran menjadi periode belajar. Hanya satu term yang boleh aktif dalam satu tahun ajaran."
    >
        <x-slot:actions>
            <a href="{{ route('admin.terms.create') }}" class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Tambah term</a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar :items="$filterItems" />

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <caption class="sr-only">Daftar term</caption>
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-5 py-3">Term</th>
                        <th scope="col" class="px-5 py-3">Tahun ajaran</th>
                        <th scope="col" class="px-5 py-3">Periode</th>
                        <th scope="col" class="px-5 py-3">Assignment</th>
                        <th scope="col" class="px-5 py-3">Status</th>
                        <th scope="col" class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($terms as $term)
                        <tr>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-950">{{ $term->sequence }}. {{ $term->label }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $term->academicYear?->label ?? '—' }}</td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $term->starts_on->format('d M Y') }} — {{ $term->ends_on->format('d M Y') }}</td>
                            <td class="px-5 py-4 text-xs text-slate-600">{{ $term->teaching_assignments_count }}</td>
                            <td class="px-5 py-4"><x-admin.status-badge :status="$term->status->value" /></td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a href="{{ route('admin.terms.show', $term) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Detail</a>
                                    <a href="{{ route('admin.terms.edit', $term) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Edit</a>
                                    <form method="POST" action="{{ route('admin.terms.status.update', $term) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $term->isActive() ? \App\Enums\TermStatus::Closed->value : \App\Enums\TermStatus::Active->value }}">
                                        <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                            {{ $term->isActive() ? 'Tutup' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-4">
                                <x-admin.empty-state
                                    label="Belum ada term"
                                    message="Term diperlukan sebelum assignment mengajar dapat dikaitkan ke periode belajar."
                                    action-label="Tambah term"
                                    action-route="admin.terms.create"
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-pagination :paginator="$terms" />
@endsection

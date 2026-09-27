@props([
    'sections' => [],
    'percentage' => 0,
])

@php
    $sectionLabels = [
        'biodata' => 'Biodata siswa',
        'guardians' => 'Orang tua / wali',
        'documents' => 'Dokumen pendukung',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm']) }}>
    <div class="flex items-center justify-between gap-4">
        <h2 class="font-semibold text-slate-950">Kelengkapan pendaftaran</h2>
        <p class="text-2xl font-bold tracking-tight text-slate-950">{{ $percentage }}%</p>
    </div>

    <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-slate-200" role="img"
         aria-label="Kelengkapan pendaftaran {{ $percentage }} persen">
        <div class="h-full rounded-full {{ $percentage === 100 ? 'bg-emerald-500' : 'bg-cyan-600' }}" style="width: {{ max(0, min(100, $percentage)) }}%"></div>
    </div>

    <ul class="mt-5 space-y-2">
        @foreach ($sections as $key => $section)
            <li class="flex items-center justify-between gap-3 text-sm">
                <span class="flex items-center gap-2 font-medium text-slate-800">
                    <span class="h-2 w-2 shrink-0 rounded-full {{ $section['complete'] ? 'bg-emerald-500' : 'bg-amber-500' }}" aria-hidden="true"></span>
                    {{ $sectionLabels[$key] ?? $key }}
                </span>
                <span class="shrink-0 text-xs font-semibold {{ $section['complete'] ? 'text-emerald-700' : 'text-amber-700' }}">
                    {{ $section['complete'] ? 'Lengkap' : $section['ratio'].'%' }}
                </span>
            </li>
        @endforeach
    </ul>
</div>

{{--
    PPDB applicant navigation.

    Two groups, matching how a candidate actually works: the account itself, and
    the registration divided into the sections that can be completed
    independently. Every link only renders when its route exists, and the
    current one is marked from the route name, so a candidate who is deep in
    "Orang tua / wali" can still see where they are.
--}}
@php
    $grouped = [
        [
            'label' => 'Akun',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'ppdb.portal.dashboard', 'pattern' => 'dashboard.ppdb'],
                ['label' => 'Status pendaftaran', 'route' => 'ppdb.portal.status', 'pattern' => 'ppdb.portal.status'],
                ['label' => 'Notifikasi', 'route' => 'ppdb.portal.notifications', 'pattern' => 'ppdb.portal.notifications'],
            ],
        ],
        [
            'label' => 'Pendaftaran',
            'items' => [
                ['label' => 'Biodata siswa', 'route' => 'ppdb.portal.biodata', 'pattern' => 'ppdb.portal.biodata'],
                ['label' => 'Orang tua / wali', 'route' => 'ppdb.portal.guardians', 'pattern' => 'ppdb.portal.guardians'],
                ['label' => 'Dokumen pendukung', 'route' => 'ppdb.portal.documents', 'pattern' => 'ppdb.portal.documents*'],
                ['label' => 'Review data', 'route' => 'ppdb.portal.review', 'pattern' => 'ppdb.portal.review'],
                ['label' => 'Ajukan pendaftaran', 'route' => 'ppdb.portal.review', 'pattern' => 'ppdb.portal.submit'],
            ],
        ],
    ];
@endphp

<nav class="space-y-6" aria-label="Navigasi pendaftar PPDB">
    @foreach ($grouped as $group)
        @php
            $visible = array_values(array_filter(
                $group['items'],
                static fn (array $item): bool => Route::has($item['route']),
            ));
        @endphp

        @if ($visible !== [])
            <div>
                <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $group['label'] }}</p>
                <ul class="mt-2 space-y-0.5">
                    @foreach ($visible as $item)
                        @php $active = request()->routeIs($item['pattern']); @endphp
                        <li>
                            <a
                                href="{{ route($item['route']) }}"
                                @if ($active) aria-current="page" @endif
                                class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 {{ $active ? 'bg-cyan-50 text-cyan-900' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950' }}"
                            >
                                @if ($active)
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-cyan-600" aria-hidden="true"></span>
                                @else
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-transparent" aria-hidden="true"></span>
                                @endif
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach

    <div>
        <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Lainnya</p>
        <ul class="mt-2 space-y-0.5">
            <li>
                <a href="{{ route('news.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950">Berita sekolah</a>
            </li>
            <li>
                <a href="{{ route('home') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-950">Situs publik</a>
            </li>
        </ul>
    </div>
</nav>

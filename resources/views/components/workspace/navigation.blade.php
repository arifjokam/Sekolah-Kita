@props([
    'items' => [],
    'label' => 'Navigasi akun',
])

{{--
    The role workspace sidebar for teachers and students.

    Same rules as the administrator navigation: a link renders only when its
    route exists, and the current item is marked from a route pattern so nested
    screens stay highlighted. It is data rather than markup so a teacher and a
    student never see each other's destinations.
--}}
<nav class="space-y-1" aria-label="{{ $label }}">
    @foreach ($items as $item)
        @continue (! Route::has($item['route']))
        @php $active = request()->routeIs($item['pattern']); @endphp
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
    @endforeach
</nav>

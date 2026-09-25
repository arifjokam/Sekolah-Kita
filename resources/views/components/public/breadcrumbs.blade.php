@props(['items' => []])

<nav aria-label="Breadcrumb" class="mb-6 text-sm text-slate-500">
    <ol class="flex flex-wrap items-center gap-2">
        <li><a href="{{ route('home') }}" class="hover:text-cyan-700">Beranda</a></li>
        @foreach ($items as $label => $url)
            <li aria-hidden="true" class="text-slate-300">/</li>
            <li @if ($loop->last) aria-current="page" @endif>
                @if ($loop->last)
                    <span class="font-medium text-slate-700">{{ $label }}</span>
                @else
                    <a href="{{ $url }}" class="hover:text-cyan-700">{{ $label }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

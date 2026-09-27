@props([
    'items' => [],
    'action' => null,
])

{{--
    Standard list filter bar.

    The form is a GET form with `withQueryString()` on the paginator, so a
    filter survives pagination and a cleared filter returns to the plain list.
--}}
<form method="GET" {{ $attributes->merge(['class' => 'mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4']) }}>
    @foreach ($items as $item)
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            <span>{{ $item['label'] }}</span>

            @if (($item['type'] ?? 'text') === 'select')
                <select name="{{ $item['name'] }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    <option value="">{{ $item['placeholder'] ?? 'Semua' }}</option>
                    @foreach ($item['options'] as $optionValue => $optionLabel)
                        <option value="{{ $optionValue }}" @selected((string) request($item['name']) === (string) $optionValue)>{{ $optionLabel }}</option>
                    @endforeach
                </select>
            @elseif (($item['type'] ?? 'text') === 'number')
                <input type="number" name="{{ $item['name'] }}" value="{{ request($item['name']) }}" min="{{ $item['min'] ?? 1 }}" placeholder="{{ $item['placeholder'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
            @else
                <input type="search" name="{{ $item['name'] }}" value="{{ request($item['name']) }}" placeholder="{{ $item['placeholder'] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm">
            @endif
        </label>
    @endforeach

    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
        <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Terapkan filter</button>

        @if (collect($items)->contains(fn (array $item): bool => filled(request($item['name']))))
            <a href="{{ url()->current() }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Reset</a>
        @endif

        @if ($action)
            <div class="ml-auto">{{ $action }}</div>
        @endif
    </div>
</form>

@props(['status'])

@php
    $statusValue = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $statusLabel = $status instanceof \App\Enums\ContactMessageStatus || $status instanceof \App\Enums\PublicationStatus || $status instanceof \App\Enums\GalleryItemStatus ? $status->label() : ucfirst($statusValue);
    $statusClass = match ($statusValue) {
        'published' => 'bg-emerald-100 text-emerald-800',
        'responded' => 'bg-blue-100 text-blue-800',
        'in_review' => 'bg-amber-100 text-amber-800',
        'spam', 'hidden', 'archived' => 'bg-slate-200 text-slate-700',
        default => 'bg-cyan-100 text-cyan-800',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold '.$statusClass]) }}>{{ $statusLabel }}</span>

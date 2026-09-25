@props(['title' => 'Periksa kembali isian formulir.'])

@if ($errors->any())
    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800" role="alert" tabindex="-1">
        <p class="font-semibold">{{ $title }}</p>
        <ul class="mt-2 list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@extends('layouts.app')

@section('title', ($newsArticle ? 'Edit' : 'Buat').' berita · Admin')

@section('content')
    <div class="mb-8"><a href="{{ route('admin.news.index') }}" class="text-sm font-semibold text-cyan-700 hover:text-cyan-900">← Kembali ke berita</a><h1 class="mt-4 text-3xl font-bold tracking-tight text-slate-950">{{ $newsArticle ? 'Edit berita' : 'Buat berita' }}</h1><p class="mt-2 text-sm text-slate-600">Isi disimpan sebagai draf. Gunakan tombol terbitkan setelah meninjau konten.</p></div>

    <form method="POST" action="{{ $newsArticle ? route('admin.news.update', $newsArticle) : route('admin.news.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if ($newsArticle) @method('PUT') @endif
        <x-form.error-summary />
        <div class="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
            <div class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div><label for="title" class="block text-sm font-semibold text-slate-800">Judul <span class="text-rose-700">*</span></label><input id="title" name="title" value="{{ old('title', $newsArticle?->title) }}" required maxlength="200" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><x-form.field-error name="title" /></div>
                <div><label for="slug" class="block text-sm font-semibold text-slate-800">Slug URL</label><input id="slug" name="slug" value="{{ old('slug', $newsArticle?->slug) }}" maxlength="220" placeholder="dibuat-dari-judul" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><p class="mt-1 text-xs text-slate-500">Kosongkan untuk membuat slug dari judul. Slug tidak boleh mengandung data pribadi.</p><x-form.field-error name="slug" /></div>
                <div><label for="excerpt" class="block text-sm font-semibold text-slate-800">Ringkasan <span class="text-rose-700">*</span></label><textarea id="excerpt" name="excerpt" rows="3" required maxlength="500" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">{{ old('excerpt', $newsArticle?->excerpt) }}</textarea><x-form.field-error name="excerpt" /></div>
                <div><label for="body" class="block text-sm font-semibold text-slate-800">Isi berita <span class="text-rose-700">*</span></label><textarea id="body" name="body" rows="14" required maxlength="20000" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm leading-7">{{ old('body', $newsArticle?->body) }}</textarea><p class="mt-1 text-xs text-slate-500">Isi disimpan sebagai teks biasa dan di-escape saat ditampilkan.</p><x-form.field-error name="body" /></div>
            </div>
            <aside class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div><h2 class="text-lg font-bold text-slate-950">Cover dan privasi</h2><p class="mt-2 text-sm leading-6 text-slate-600">Gambar disimpan private terlebih dahulu. Metadata gambar dihapus melalui re-encode.</p></div>
                <div><label for="cover_image" class="block text-sm font-semibold text-slate-800">Gambar cover</label><input id="cover_image" name="cover_image" type="file" accept=".jpg,.jpeg,.png,.webp" class="mt-2 block w-full text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-cyan-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-cyan-800"><x-form.field-error name="cover_image" /></div>
                <label class="flex items-start gap-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-900"><input type="checkbox" name="privacy_review_confirmed" value="1" class="mt-1 rounded border-amber-300 text-cyan-700 focus:ring-cyan-600" @checked(old('privacy_review_confirmed'))><span>Saya telah meninjau gambar cover dan konten berita untuk memastikan tidak membocorkan data pribadi.</span></label><x-form.field-error name="privacy_review_confirmed" />
                <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600"><p class="font-semibold text-slate-800">Status saat ini</p><p class="mt-1">{{ $newsArticle?->status?->label() ?? 'Draf baru' }}</p></div>
                <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-cyan-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-cyan-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Simpan berita</button>
            </aside>
        </div>
    </form>
@endsection

@extends('layouts.app')

@section('title', 'Ringkasan administrator · Sekolah Kita')

@section('content')
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-cyan-700">Area administrator</p>
        <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">Kelola website sekolah</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Publication workflow untuk konten publik, direktori guru, profil sekolah, galeri, dan pesan kontak. Konten berstatus draf tidak tampil di website publik.</p>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('admin.school-profile.edit') }}" class="rounded-2xl border border-slate-200 p-5 transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"><p class="font-semibold text-slate-950">Profil sekolah</p><p class="mt-2 text-sm text-slate-600">Identitas, kontak, visi, misi, dan gambar utama.</p></a>
            <a href="{{ route('admin.news.index') }}" class="rounded-2xl border border-slate-200 p-5 transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"><p class="font-semibold text-slate-950">Berita</p><p class="mt-2 text-sm text-slate-600">Tulis, terbitkan, dan arsipkan berita.</p></a>
            <a href="{{ route('admin.achievements.index') }}" class="rounded-2xl border border-slate-200 p-5 transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"><p class="font-semibold text-slate-950">Prestasi</p><p class="mt-2 text-sm text-slate-600">Publikasikan informasi prestasi yang anonim dan aman.</p></a>
            <a href="{{ route('admin.gallery-albums.index') }}" class="rounded-2xl border border-slate-200 p-5 transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"><p class="font-semibold text-slate-950">Galeri</p><p class="mt-2 text-sm text-slate-600">Kelola album dan media yang telah ditinjau.</p></a>
            <a href="{{ route('admin.teachers.index') }}" class="rounded-2xl border border-slate-200 p-5 transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"><p class="font-semibold text-slate-950">Guru publik</p><p class="mt-2 text-sm text-slate-600">Atur profil profesional tanpa membuka data privat.</p></a>
            <a href="{{ route('admin.contact-messages.index') }}" class="rounded-2xl border border-slate-200 p-5 transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"><p class="font-semibold text-slate-950">Pesan kontak</p><p class="mt-2 text-sm text-slate-600">Tinjau pesan yang dikirim melalui situs publik.</p></a>
        </div>
    </div>
@endsection

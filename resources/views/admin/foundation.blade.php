@extends('layouts.app')

@section('title', 'Area administrator · Sekolah Kita')

@section('content')
    @php
        $breadcrumbs = [
            ['label' => 'Dashboard', 'route' => 'dashboard.administrator'],
            ['label' => 'Ringkasan administrator'],
        ];

        $entries = [
            ['label' => 'Tahun ajaran', 'description' => 'Fondasi seluruh data akademik dan satu-satunya tahun ajaran aktif.', 'route' => 'admin.academic-years.index'],
            ['label' => 'Kelas', 'description' => 'Rombongan belajar per tahun ajaran beserta wali danmuridnya.', 'route' => 'admin.classes.index'],
            ['label' => 'Guru', 'description' => 'Data kepegawaian guru dan status akses portalnya.', 'route' => 'admin.teachers.index'],
            ['label' => 'Siswa', 'description' => 'Data siswa, wali, dan penempatan kelas.', 'route' => 'admin.students.index'],
            ['label' => 'Mata pelajaran', 'description' => 'Daftar pelajaran yang dipakai pada assignment mengajar.', 'route' => 'admin.subjects.index'],
            ['label' => 'Assignment', 'description' => 'Penugasan guru mengajar dan wali kelas.', 'route' => 'admin.teaching-assignments.index'],
            ['label' => 'Periode PPDB', 'description' => 'Jendela pendaftaran dan kuota penerimaan.', 'route' => 'admin.ppdb.periods.index'],
            ['label' => 'Review PPDB', 'description' => 'Antrean keputusan dan konversi pendaftar menjadi siswa.', 'route' => 'admin.ppdb.review.index'],
            ['label' => 'Berita', 'description' => 'Tulis, terbitkan, dan arsipkan berita sekolah.', 'route' => 'admin.news.index'],
            ['label' => 'Profil sekolah', 'description' => 'Identitas, kontak, visi, dan misi sekolah.', 'route' => 'admin.school-profile.edit'],
            ['label' => 'Audit log', 'description' => 'Jejak audit setiap perubahan administrasi.', 'route' => 'admin.audit-logs.index'],
        ];
    @endphp

    <x-admin.page-header
        eyebrow="Area administrator"
        title="Kelola Sekolah Kita"
        description="Semua modul sekolah dalam satu tempat. Gunakan navigasi di samping untuk berpindah modul, atau pilih pintasan di bawah."
    />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($entries as $entry)
            @if (Route::has($entry['route']))
                <a
                    href="{{ route($entry['route']) }}"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-cyan-300 hover:bg-cyan-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600"
                >
                    <p class="font-semibold text-slate-950">{{ $entry['label'] }}</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $entry['description'] }}</p>
                </a>
            @endif
        @endforeach
    </div>
@endsection

@extends('layouts.app')

@section('title', 'Dashboard · Sekolah Kita')

@section('content')
    {{--
        The role landing pages own the dashboard, and the /dashboard route sends
        the account to the right one. This view is only reachable if an account
        somehow holds none of the three fixed roles, which the role middleware
        already prevents; it is kept as an explicit, readable terminal state.
    --}}
    <x-admin.page-header
        eyebrow="Dashboard"
        title="Dashboard belum tersedia"
        description="Akun ini belum memiliki peran yang memiliki dashboard. Administrator sekolah perlu menetapkan peran akun Anda."
    />

    <x-admin.empty-state
        label="Peran akun belum ditetapkan"
        message="Setiap akun harus memiliki peran administrator, guru, atau siswa agar dapat masuk ke area kerja. Hubungi administrator sekolah untuk menetapkan peran akun."
    >
        <a href="{{ route('notifications.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Lihat notifikasi</a>
    </x-admin.empty-state>
@endsection

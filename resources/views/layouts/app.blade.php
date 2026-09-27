<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Sekolah Kita')</title>

        @fonts

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        @stack('head')
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:text-sm focus:font-semibold focus:shadow-lg">Lewati ke konten utama</a>

        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    @auth
                        <button
                            type="button"
                            data-sidebar-toggle
                            aria-controls="app-sidebar"
                            aria-expanded="false"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 text-slate-600 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 lg:hidden"
                        >
                            <span class="sr-only">Buka navigasi</span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="h-5 w-5" aria-hidden="true">
                                <path d="M3 5h14M3 10h14M3 15h14" />
                            </svg>
                        </button>
                    @endauth

                    <a href="{{ route('home') }}" class="inline-flex min-w-0 items-center gap-3 text-sm font-semibold tracking-wide text-slate-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600 focus-visible:ring-offset-2">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-cyan-700 text-sm font-bold text-white">SK</span>
                        <span class="hidden truncate sm:inline">Sekolah Kita</span>
                    </a>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="{{ route('home') }}" class="hidden text-sm font-medium text-slate-600 hover:text-cyan-700 lg:inline-flex">Lihat situs publik</a>

                    @auth
                        <a href="{{ route('notifications.index') }}" class="relative inline-flex items-center rounded-lg px-2 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-cyan-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">
                            <span class="sr-only">Notifikasi</span>
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                <path d="M10 3a4.5 4.5 0 0 0-4.5 4.5c0 3-1.5 4.5-1.5 4.5h12s-1.5-1.5-1.5-4.5A4.5 4.5 0 0 0 10 3ZM8 15.5a2 2 0 0 0 4 0" />
                            </svg>
                            @if (($unreadNotificationCount ?? 0) > 0)
                                <span class="absolute -right-0.5 -top-0.5 min-w-4 rounded-full bg-cyan-700 px-1 text-[10px] font-bold leading-4 text-white">{{ $unreadNotificationCount }}</span>
                            @endif
                        </a>

                        <span class="hidden text-right text-xs leading-4 text-slate-500 md:block">
                            <span class="block max-w-[14rem] truncate font-semibold text-slate-800">{{ auth()->user()->name }}</span>
                            {{ auth()->user()->role?->label() }}
                        </span>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-600">Keluar</button>
                        </form>
                    @endauth
                </div>
            </div>
        </header>

        @auth
            {{-- Off-canvas scrim for the mobile drawer. Hidden by default and shown by the toggle. --}}
            <div data-sidebar-overlay hidden class="fixed inset-0 z-40 bg-slate-900/40 lg:hidden"></div>
        @endauth

        <div class="mx-auto flex w-full max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:px-8">
            @auth
                <aside
                    id="app-sidebar"
                    data-sidebar
                    class="fixed inset-y-0 left-0 z-50 w-72 -translate-x-full overflow-y-auto border-r border-slate-200 bg-white px-4 py-6 transition-transform lg:sticky lg:top-24 lg:z-auto lg:h-[calc(100vh-7rem)] lg:w-64 lg:shrink-0 lg:translate-x-0 lg:border-r-0 lg:bg-transparent lg:px-0 lg:py-0"
                    aria-label="Navigasi area kerja"
                >
                    <div class="lg:sticky lg:top-0">
                        {{--
                            The admin shell is gated, not role-matched. @role is a
                            Spatie directive that this application does not have,
                            so an unknown directive would silently render the
                            whole administrator navigation for every account.
                        --}}
                        @if (auth()->user()?->can('access-administrator-area'))
                            <x-admin.navigation />
                        @elseif (auth()->user()?->isCalon())
                            <x-candidate.navigation />
                        @elseif (auth()->user()?->isGuru())
                            <x-workspace.navigation
                                :items="[
                                    ['label' => 'Dashboard', 'route' => 'dashboard.guru', 'pattern' => 'dashboard.guru'],
                                    ['label' => 'Kelas saya', 'route' => 'guru.classes.index', 'pattern' => 'guru.classes.*'],
                                    ['label' => 'Jadwal pelajaran', 'route' => 'guru.jadwal.index', 'pattern' => 'guru.jadwal.*'],
                                    ['label' => 'Absensi', 'route' => 'guru.absensi.index', 'pattern' => 'guru.absensi.*'],
                                    ['label' => 'Laporan bulanan', 'route' => 'guru.absensi.report', 'pattern' => 'guru.absensi.report*'],
                                    ['label' => 'Biodata guru', 'route' => 'guru.profil', 'pattern' => 'guru.profil*'],
                                ]"
                            />
                        @else
                            <x-workspace.navigation
                                :items="[
                                    ['label' => 'Dashboard', 'route' => 'dashboard.siswa', 'pattern' => 'dashboard.siswa'],
                                    ['label' => 'Jadwal pelajaran', 'route' => 'siswa.jadwal.index', 'pattern' => 'siswa.jadwal.*'],
                                    ['label' => 'Absensi saya', 'route' => 'siswa.absensi.index', 'pattern' => 'siswa.absensi.*'],
                                    ['label' => 'Biodata siswa', 'route' => 'siswa.profil', 'pattern' => 'siswa.profil*'],
                                    ['label' => 'Notifikasi', 'route' => 'notifications.index', 'pattern' => 'notifications.*'],
                                    ['label' => 'Situs publik', 'route' => 'home', 'pattern' => 'home'],
                                ]"
                            />
                        @endif
                    </div>
                </aside>
            @endauth

            <main id="main-content" class="min-w-0 flex-1">
                @if (session('status'))
                    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
                @endif

                @isset($breadcrumbs)
                    <nav aria-label="Remah roti" class="mb-4">
                        <ol class="flex flex-wrap items-center gap-1 text-sm text-slate-500">
                            @foreach ($breadcrumbs as $crumb)
                                <li class="flex items-center gap-1">
                                    @if (! $loop->first)
                                        <span aria-hidden="true" class="text-slate-300">/</span>
                                    @endif
                                    @if (! $loop->last && ! empty($crumb['route']))
                                        <a href="{{ route($crumb['route'], $crumb['params'] ?? []) }}" class="font-medium hover:text-cyan-700">{{ $crumb['label'] }}</a>
                                    @else
                                        <span class="font-medium text-slate-700" @if ($loop->last) aria-current="page" @endif>{{ $crumb['label'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                @endisset

                @yield('content')
            </main>
        </div>

        @stack('scripts')
    </body>
</html>

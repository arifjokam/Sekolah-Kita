{{--
    Grouped administrator navigation.

    Every entry is expressed as data so the shell can apply the same rules to
    each one: the link only renders when the named route exists, and each item
    can carry a gate ability so an unauthorized link is never rendered. Marking
    the current item is done with a route pattern, which keeps nested screens
    (index, create, edit) highlighted correctly.
--}}
@php
    use App\Models\AcademicYear;
    use App\Models\ClassModel;
    use App\Models\Subject;
    use App\Models\Teacher;
    use App\Models\Term;

    $groups = [
        [
            'label' => 'Ringkasan',
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard.administrator', 'pattern' => 'dashboard.*'],
                ['label' => 'Ringkasan modul', 'route' => 'admin.foundation', 'pattern' => 'admin.foundation'],
            ],
        ],
        [
            'label' => 'Konten',
            'items' => [
                ['label' => 'Berita', 'route' => 'admin.news.index', 'pattern' => 'admin.news.*'],
                ['label' => 'Prestasi', 'route' => 'admin.achievements.index', 'pattern' => 'admin.achievements.*'],
                ['label' => 'Galeri', 'route' => 'admin.gallery-albums.index', 'pattern' => 'admin.gallery-albums.*'],
                ['label' => 'Profil sekolah', 'route' => 'admin.school-profile.edit', 'pattern' => 'admin.school-profile.*'],
                ['label' => 'Guru publik', 'route' => 'admin.teacher-directory.index', 'pattern' => 'admin.teacher-directory.*'],
                ['label' => 'Pesan kontak', 'route' => 'admin.contact-messages.index', 'pattern' => 'admin.contact-messages.*'],
            ],
        ],
        [
            'label' => 'Akademik',
            'items' => [
                ['label' => 'Tahun ajaran', 'route' => 'admin.academic-years.index', 'pattern' => 'admin.academic-years.*'],
                ['label' => 'Semester', 'route' => 'admin.terms.index', 'pattern' => 'admin.terms.*'],
                ['label' => 'Kelas', 'route' => 'admin.classes.index', 'pattern' => 'admin.classes.*'],
                ['label' => 'Mata pelajaran', 'route' => 'admin.subjects.index', 'pattern' => 'admin.subjects.*'],
                ['label' => 'Guru', 'route' => 'admin.teachers.index', 'pattern' => 'admin.teachers.*'],
                ['label' => 'Siswa', 'route' => 'admin.students.index', 'pattern' => 'admin.students.*'],
                ['label' => 'Enrollment', 'route' => 'admin.enrollments.index', 'pattern' => 'admin.enrollments.*'],
                ['label' => 'Jadwal pelajaran', 'route' => 'admin.class-schedules.index', 'pattern' => 'admin.class-schedules.*'],
                ['label' => 'Assignment', 'route' => 'admin.teaching-assignments.index', 'pattern' => 'admin.teaching-assignments.*'],
                ['label' => 'Wali kelas', 'route' => 'admin.class-teacher-assignments.index', 'pattern' => 'admin.class-teacher-assignments.*'],
            ],
        ],
        [
            'label' => 'PPDB',
            'items' => [
                ['label' => 'Periode PPDB', 'route' => 'admin.ppdb.periods.index', 'pattern' => 'admin.ppdb.periods.*'],
                ['label' => 'Pendaftar', 'route' => 'admin.ppdb.applications.index', 'pattern' => 'admin.ppdb.applications.*'],
                ['label' => 'Review', 'route' => 'admin.ppdb.review.index', 'pattern' => 'admin.ppdb.review.*'],
                ['label' => 'Dokumen', 'route' => 'admin.ppdb.review.index', 'pattern' => 'admin.ppdb.documents.*'],
                ['label' => 'Status', 'route' => 'admin.ppdb.applications.index', 'pattern' => 'admin.ppdb.applications.*'],
            ],
        ],
        [
            'label' => 'Sistem',
            'items' => [
                ['label' => 'Notifications', 'route' => 'notifications.index', 'pattern' => 'notifications.*'],
                ['label' => 'Audit log', 'route' => 'admin.audit-logs.index', 'pattern' => 'admin.audit-logs.*', 'ability' => 'view-audit-log'],
                ['label' => 'Health / readiness', 'route' => 'health.ready', 'pattern' => 'health.*'],
            ],
        ],
    ];

    $routeExists = static fn (string $name): bool => Route::has($name);
@endphp

<nav class="space-y-6" aria-label="Navigasi administrator">
    @foreach ($groups as $group)
        @php
            $visible = array_values(array_filter(
                $group['items'],
                static fn (array $item): bool => $routeExists($item['route'])
                    && (! isset($item['ability']) || auth()->user()?->can($item['ability'])),
            ));
        @endphp

        @if ($visible !== [])
            <div>
                <p class="px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $group['label'] }}</p>
                <ul class="mt-2 space-y-0.5">
                    @foreach ($visible as $item)
                        @php $active = request()->routeIs($item['pattern']); @endphp
                        <li>
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
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
</nav>

<?php

use App\Http\Controllers\Admin\AcademicYearController as AdminAcademicYearController;
use App\Http\Controllers\Admin\AchievementController as AdminAchievementController;
use App\Http\Controllers\Admin\AdmissionApplicationController as AdminAdmissionApplicationController;
use App\Http\Controllers\Admin\AdmissionDocumentController as AdminAdmissionDocumentController;
use App\Http\Controllers\Admin\AdmissionPeriodController as AdminAdmissionPeriodController;
use App\Http\Controllers\Admin\ClassController as AdminClassController;
use App\Http\Controllers\Admin\ClassScheduleController as AdminClassScheduleController;
use App\Http\Controllers\Admin\ClassTeacherAssignmentController as AdminClassTeacherAssignmentController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\EnrollmentController as AdminEnrollmentController;
use App\Http\Controllers\Admin\GalleryAlbumController as AdminGalleryAlbumController;
use App\Http\Controllers\Admin\GalleryItemController as AdminGalleryItemController;
use App\Http\Controllers\Admin\NewsArticleController as AdminNewsArticleController;
use App\Http\Controllers\Admin\NewsArticleImageController as AdminNewsArticleImageController;
use App\Http\Controllers\Admin\SchoolProfileController as AdminSchoolProfileController;
use App\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Admin\SubjectController as AdminSubjectController;
use App\Http\Controllers\Admin\TeacherController as AdminTeacherController;
use App\Http\Controllers\Admin\TeacherDirectoryController as AdminTeacherDirectoryController;
use App\Http\Controllers\Admin\TeachingAssignmentController as AdminTeachingAssignmentController;
use App\Http\Controllers\Admin\TermController as AdminTermController;
use App\Http\Controllers\AdministratorFoundationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Ppdb\ApplicantBiodataController;
use App\Http\Controllers\Ppdb\ApplicantDocumentController;
use App\Http\Controllers\Ppdb\ApplicantGuardianController;
use App\Http\Controllers\Ppdb\ApplicantPortalController;
use App\Http\Controllers\Ppdb\RegisteredApplicantController;
use App\Http\Controllers\Public\AchievementController;
use App\Http\Controllers\Public\AdmissionController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsArticleController;
use App\Http\Controllers\Public\SchoolProfileController;
use App\Http\Controllers\Public\TeacherDirectoryController;
use App\Http\Controllers\Student\StudentAttendanceController;
use App\Http\Controllers\Student\StudentProfileController;
use App\Http\Controllers\Student\StudentScheduleController;
use App\Http\Controllers\Teacher\TeacherAttendanceController;
use App\Http\Controllers\Teacher\TeacherProfileController;
use App\Http\Controllers\Teacher\TeacherScheduleController;
use App\Http\Controllers\TeacherWorkspaceController;
use Illuminate\Support\Facades\Route;

/*
| Role landing pages.
|
| A role has one canonical home so the authenticated layout, the breadcrumb
| trail and the "back to start" affordance are never ambiguous. The shared
| /dashboard route still exists and renders the same views, which keeps the
| post-login redirect in AuthenticatedSessionController stable.
*/
Route::get('/', HomeController::class)->name('home');
Route::get('/health', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');
Route::get('/profil-sekolah', [SchoolProfileController::class, 'show'])->name('school-profile.show');
Route::get('/berita', [NewsArticleController::class, 'index'])->name('news.index');
Route::get('/berita/{slug}', [NewsArticleController::class, 'show'])->name('news.show');
Route::get('/prestasi', [AchievementController::class, 'index'])->name('achievements.index');
Route::get('/prestasi/{slug}', [AchievementController::class, 'show'])->name('achievements.show');
Route::get('/galeri', [GalleryController::class, 'index'])->name('gallery.index');
Route::get('/galeri/{slug}', [GalleryController::class, 'show'])->name('gallery.show');
Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:guru'])->group(function (): void {
    /*
    | The teacher's own workspace.
    |
    | A teacher is not an administrator, so the class roster, the timetable, the
    | register and the monthly report they are allowed to see are exposed here.
    | ClassPolicy::view and ClassPolicy::viewRoster resolve the class scope from
    | the teacher's own assignments.
    |
    | This whole group is declared before the public /guru/{slug} directory route
    | on purpose: "/guru/kelas", "/guru/jadwal" and "/guru/profil" are static
    | segments, and the directory wildcard would otherwise capture them and
    | return a 404 — for a signed-in teacher, which is exactly the class of bug a
    | route-ordering mistake produces and a status-code check alone would hide.
    */
    Route::get('/guru/kelas', [TeacherWorkspaceController::class, 'classes'])->name('guru.classes.index');
    Route::get('/guru/kelas/{class}', [TeacherWorkspaceController::class, 'show'])->name('guru.classes.show');

    Route::get('/guru/profil', [TeacherProfileController::class, 'show'])->name('guru.profil');
    Route::put('/guru/profil', [TeacherProfileController::class, 'update'])->name('guru.profil.update');

    Route::get('/guru/jadwal', [TeacherScheduleController::class, 'index'])->name('guru.jadwal.index');

    /*
     | The literal "laporan" segment is declared before the {session} wildcard:
     | "/guru/absensi/laporan" would otherwise be captured as a session id.
     */
    Route::get('/guru/absensi/laporan', [TeacherAttendanceController::class, 'report'])->name('guru.absensi.report');
    Route::get('/guru/absensi/laporan/cetak', [TeacherAttendanceController::class, 'printReport'])
        ->name('guru.absensi.report.print');
    Route::get('/guru/absensi', [TeacherAttendanceController::class, 'index'])->name('guru.absensi.index');
    Route::post('/guru/absensi/jadwal/{classSchedule}', [TeacherAttendanceController::class, 'open'])
        ->middleware('throttle:attendance-mark')
        ->name('guru.absensi.open');
    Route::get('/guru/absensi/{session}', [TeacherAttendanceController::class, 'show'])->name('guru.absensi.show');
    Route::post('/guru/absensi/{session}', [TeacherAttendanceController::class, 'store'])
        ->middleware('throttle:attendance-mark')
        ->name('guru.absensi.store');
    Route::put('/guru/absensi/{session}/koreksi', [TeacherAttendanceController::class, 'correct'])
        ->middleware('throttle:attendance-correct')
        ->name('guru.absensi.correct');
});

Route::get('/guru', [TeacherDirectoryController::class, 'index'])->name('teachers.index');
Route::get('/guru/{slug}', [TeacherDirectoryController::class, 'show'])->name('teachers.show');
Route::get('/kontak', [ContactController::class, 'create'])->name('contact.create');
Route::post('/kontak', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

Route::get('/ppdb', [AdmissionController::class, 'index'])->name('ppdb.index');
Route::get('/ppdb/daftar', [AdmissionController::class, 'create'])->name('ppdb.application.create');
Route::post('/ppdb/daftar', [AdmissionController::class, 'store'])
    ->middleware('throttle:ppdb-submit')
    ->name('ppdb.application.store');
Route::get('/ppdb/status/{token}', [AdmissionController::class, 'status'])
    ->middleware('throttle:ppdb-status')
    ->name('ppdb.status.show');
Route::post('/ppdb/status/{token}/koreksi', [AdmissionController::class, 'correction'])
    ->middleware('throttle:ppdb-status')
    ->name('ppdb.status.correct');
Route::post('/ppdb/status/{token}/dokumen', [AdmissionController::class, 'uploadDocument'])
    ->middleware('throttle:ppdb-upload')
    ->name('ppdb.status.document.store');
Route::post('/ppdb/status/{token}/hentikan', [AdmissionController::class, 'withdraw'])
    ->middleware('throttle:ppdb-status')
    ->name('ppdb.status.withdraw');

/*
|--------------------------------------------------------------------------
| PPDB applicant portal
|--------------------------------------------------------------------------
|
| A second, self-service way to apply, for a candidate who wants to complete
| the application progressively instead of sending one form.
|
| The public routes above are untouched: a visitor can still submit the
| one-shot form without an account, and the status token flow is unchanged. The
| portal is a separate surface with its own account, its own progressive
| sections and its own authorization, and the two never share state except the
| review queue an administrator already had.
|
| Everything under /ppdb/akun is role:calon and authorised on the *caller's own*
| application. There is deliberately no application id in these URLs, so a
| candidate cannot address another candidate's data by editing a path.
|
*/

Route::middleware(['guest'])->group(function (): void {
    Route::get('/ppdb/daftar-akun', [RegisteredApplicantController::class, 'create'])
        ->name('ppdb.portal.register.create');
    Route::post('/ppdb/daftar-akun', [RegisteredApplicantController::class, 'store'])
        ->middleware('throttle:ppdb-register')
        ->name('ppdb.portal.register.store');
});

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:calon'])
    ->prefix('ppdb/akun')
    ->name('ppdb.portal.')
    ->group(function (): void {
        Route::get('/', [ApplicantPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/biodata', [ApplicantBiodataController::class, 'edit'])->name('biodata');
        Route::put('/biodata/{application}', [ApplicantBiodataController::class, 'update'])
            ->middleware('throttle:ppdb-portal-write')
            ->name('biodata.update');
        Route::get('/wali', [ApplicantGuardianController::class, 'index'])->name('guardians');
        Route::post('/wali/{application}', [ApplicantGuardianController::class, 'store'])
            ->middleware('throttle:ppdb-portal-write')
            ->name('guardians.store');
        Route::put('/wali/{application}/{guardian}', [ApplicantGuardianController::class, 'update'])
            ->middleware('throttle:ppdb-portal-write')
            ->name('guardians.update');
        Route::get('/dokumen', [ApplicantDocumentController::class, 'index'])->name('documents');
        Route::post('/dokumen/{application}', [ApplicantDocumentController::class, 'store'])
            ->middleware('throttle:ppdb-portal-upload')
            ->name('documents.store');
        Route::put('/dokumen/{document}', [ApplicantDocumentController::class, 'replace'])
            ->middleware('throttle:ppdb-portal-upload')
            ->name('documents.replace');
        Route::get('/dokumen/{document}/unduh', [ApplicantDocumentController::class, 'download'])
            ->name('documents.download');
        Route::get('/review', [ApplicantPortalController::class, 'review'])->name('review');
        Route::post('/review', [ApplicantPortalController::class, 'submit'])
            ->middleware('throttle:ppdb-portal-write')
            ->name('submit');
        Route::get('/status', [ApplicantPortalController::class, 'status'])->name('status');
        Route::get('/notifikasi', [ApplicantPortalController::class, 'notifications'])->name('notifications');
    });

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->middleware('throttle:password-reset')
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.update');
});

Route::middleware(['auth', 'auth.session', 'active'])->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationPromptController::class, 'show'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationPromptController::class, 'store'])
        ->middleware('throttle:verification')
        ->name('verification.send');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:verification'])
        ->name('verification.verify');
});

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:administrator,guru,siswa,calon'])->group(function (): void {
    /*
    | The shared entry point. It resolves the role landing page from the
    | session so the post-login redirect in AuthenticatedSessionController
    | stays stable.
    */
    Route::get('/dashboard', [DashboardController::class, 'redirectToRoleDashboard'])->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreference'])->name('notifications.preferences.update');
});

/*
| Role landing pages.
|
| Each role owns a canonical URL, and the role middleware keeps a guru from
| opening the student landing page even though the underlying payload is scoped
| to the requesting account anyway.
*/
Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:administrator'])
    ->get('/dashboard/administrator', [DashboardController::class, 'index'])
    ->name('dashboard.administrator');

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:guru'])
    ->get('/dashboard/guru', [DashboardController::class, 'index'])
    ->name('dashboard.guru');

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:siswa'])
    ->get('/dashboard/siswa', [DashboardController::class, 'index'])
    ->name('dashboard.siswa');

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:calon'])
    ->get('/dashboard/ppdb', [DashboardController::class, 'index'])
    ->name('dashboard.ppdb');

/*
| Student workspace.
|
| Resolved entirely from the authenticated student's own records, so no student
| id appears in any of these URLs. /siswa is not shadowed by a public wildcard,
| but the same ordering rule is kept for consistency with the teacher group.
*/
Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:siswa'])->group(function (): void {
    Route::get('/siswa/profil', [StudentProfileController::class, 'show'])->name('siswa.profil');
    Route::put('/siswa/profil', [StudentProfileController::class, 'update'])->name('siswa.profil.update');
    Route::get('/siswa/jadwal', [StudentScheduleController::class, 'index'])->name('siswa.jadwal.index');
    Route::get('/siswa/absensi', [StudentAttendanceController::class, 'index'])->name('siswa.absensi.index');
});

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:guru'])->group(function (): void {
    /*
    | The teacher's own workspace.
    |
    | A teacher is not an administrator, so the class roster they are allowed to
    | see is exposed here. ClassPolicy::view and ClassPolicy::viewRoster resolve
    | the scope from the teacher's own assignments.
    */
    Route::get('/guru/kelas', [TeacherWorkspaceController::class, 'classes'])->name('guru.classes.index');
    Route::get('/guru/kelas/{class}', [TeacherWorkspaceController::class, 'show'])->name('guru.classes.show');
});

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:administrator'])
    ->get('/administrator', [AdministratorFoundationController::class, 'index'])
    ->name('admin.foundation');

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:administrator'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/school-profile', [AdminSchoolProfileController::class, 'edit'])->name('school-profile.edit');
    Route::put('/school-profile', [AdminSchoolProfileController::class, 'update'])->name('school-profile.update');

    Route::resource('news', AdminNewsArticleController::class)
        ->parameters(['news' => 'newsArticle'])
        ->except(['show', 'destroy']);
    Route::post('/news/images', [AdminNewsArticleImageController::class, 'store'])
        ->middleware('throttle:news-image-upload')
        ->name('news.images.store');
    Route::post('/news/{newsArticle}/images', [AdminNewsArticleImageController::class, 'storeForArticle'])
        ->middleware('throttle:news-image-upload')
        ->name('news.images.store-for-article');
    Route::post('/news/{newsArticle}/publish', [AdminNewsArticleController::class, 'publish'])->name('news.publish');
    Route::post('/news/{newsArticle}/unpublish', [AdminNewsArticleController::class, 'unpublish'])->name('news.unpublish');
    Route::post('/news/{newsArticle}/archive', [AdminNewsArticleController::class, 'archive'])->name('news.archive');

    Route::resource('achievements', AdminAchievementController::class)
        ->parameters(['achievements' => 'achievement'])
        ->except(['show', 'destroy']);
    Route::post('/achievements/{achievement}/publish', [AdminAchievementController::class, 'publish'])->name('achievements.publish');
    Route::post('/achievements/{achievement}/unpublish', [AdminAchievementController::class, 'unpublish'])->name('achievements.unpublish');
    Route::post('/achievements/{achievement}/archive', [AdminAchievementController::class, 'archive'])->name('achievements.archive');

    Route::resource('gallery-albums', AdminGalleryAlbumController::class)
        ->parameters(['gallery-albums' => 'galleryAlbum'])
        ->except(['show', 'destroy']);
    Route::post('/gallery-albums/{galleryAlbum}/publish', [AdminGalleryAlbumController::class, 'publish'])->name('gallery-albums.publish');
    Route::post('/gallery-albums/{galleryAlbum}/unpublish', [AdminGalleryAlbumController::class, 'unpublish'])->name('gallery-albums.unpublish');
    Route::post('/gallery-albums/{galleryAlbum}/archive', [AdminGalleryAlbumController::class, 'archive'])->name('gallery-albums.archive');

    Route::scopeBindings()->group(function (): void {
        Route::get('/gallery-albums/{galleryAlbum}/items', [AdminGalleryItemController::class, 'index'])->name('gallery-albums.items.index');
        Route::get('/gallery-albums/{galleryAlbum}/items/create', [AdminGalleryItemController::class, 'create'])->name('gallery-albums.items.create');
        Route::post('/gallery-albums/{galleryAlbum}/items', [AdminGalleryItemController::class, 'store'])->name('gallery-albums.items.store');
        Route::get('/gallery-albums/{galleryAlbum}/items/{galleryItem}/edit', [AdminGalleryItemController::class, 'edit'])->name('gallery-albums.items.edit');
        Route::put('/gallery-albums/{galleryAlbum}/items/{galleryItem}', [AdminGalleryItemController::class, 'update'])->name('gallery-albums.items.update');
        Route::post('/gallery-albums/{galleryAlbum}/items/{galleryItem}/publish', [AdminGalleryItemController::class, 'publish'])->name('gallery-albums.items.publish');
        Route::post('/gallery-albums/{galleryAlbum}/items/{galleryItem}/unpublish', [AdminGalleryItemController::class, 'unpublish'])->name('gallery-albums.items.unpublish');
        Route::post('/gallery-albums/{galleryAlbum}/items/{galleryItem}/hide', [AdminGalleryItemController::class, 'hide'])->name('gallery-albums.items.hide');
        Route::post('/gallery-albums/{galleryAlbum}/items/{galleryItem}/archive', [AdminGalleryItemController::class, 'archive'])->name('gallery-albums.items.archive');
    });

    Route::get('/teacher-directory', [AdminTeacherDirectoryController::class, 'index'])->name('teacher-directory.index');
    Route::get('/teacher-directory/{teacher}/edit', [AdminTeacherDirectoryController::class, 'edit'])->name('teacher-directory.edit');
    Route::put('/teacher-directory/{teacher}', [AdminTeacherDirectoryController::class, 'update'])->name('teacher-directory.update');
    Route::post('/teacher-directory/{teacher}/publish', [AdminTeacherDirectoryController::class, 'publish'])->name('teacher-directory.publish');
    Route::post('/teacher-directory/{teacher}/unpublish', [AdminTeacherDirectoryController::class, 'unpublish'])->name('teacher-directory.unpublish');

    /*
    | Master data. Every resource is guarded by its own policy inside the
    | controllers and form requests, so the route table and the authorization
    | rules stay independently reviewable.
    */
    Route::resource('teachers', AdminTeacherController::class)
        ->parameters(['teachers' => 'teacher'])
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/teachers/{teacher}/employment-status', [AdminTeacherController::class, 'updateEmploymentStatus'])
        ->name('teachers.employment-status.update');

    Route::resource('students', AdminStudentController::class)
        ->parameters(['students' => 'student'])
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/students/{student}/lifecycle-status', [AdminStudentController::class, 'updateLifecycleStatus'])
        ->name('students.lifecycle-status.update');
    Route::post('/students/{student}/guardians', [AdminStudentController::class, 'storeGuardian'])
        ->name('students.guardians.store');
    Route::delete('/students/{student}/guardians/{studentGuardian}', [AdminStudentController::class, 'destroyGuardian'])
        ->name('students.guardians.destroy');

    Route::resource('academic-years', AdminAcademicYearController::class)
        ->parameters(['academic-years' => 'academicYear'])
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/academic-years/{academicYear}/status', [AdminAcademicYearController::class, 'updateStatus'])
        ->name('academic-years.status.update');

    Route::resource('terms', AdminTermController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/terms/{term}/status', [AdminTermController::class, 'updateStatus'])->name('terms.status.update');

    Route::resource('classes', AdminClassController::class)
        ->parameters(['classes' => 'class'])
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/classes/{class}/status', [AdminClassController::class, 'updateStatus'])->name('classes.status.update');

    Route::resource('subjects', AdminSubjectController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/subjects/{subject}/status', [AdminSubjectController::class, 'updateStatus'])->name('subjects.status.update');

    Route::resource('teaching-assignments', AdminTeachingAssignmentController::class)
        ->parameters(['teaching-assignments' => 'teachingAssignment'])
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/teaching-assignments/{teachingAssignment}/status', [AdminTeachingAssignmentController::class, 'updateStatus'])
        ->name('teaching-assignments.status.update');

    Route::resource('class-teacher-assignments', AdminClassTeacherAssignmentController::class)
        ->parameters(['class-teacher-assignments' => 'classTeacherAssignment'])
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/class-teacher-assignments/{classTeacherAssignment}/status', [AdminClassTeacherAssignmentController::class, 'updateStatus'])
        ->name('class-teacher-assignments.status.update');

    /*
    | The weekly class schedule. There is no destroy route on purpose: attendance
    | sessions reference a schedule, so a lesson is deactivated and can be
    | reactivated later.
    */
    Route::get('/class-schedules', [AdminClassScheduleController::class, 'index'])->name('class-schedules.index');
    Route::get('/class-schedules/create', [AdminClassScheduleController::class, 'create'])->name('class-schedules.create');
    Route::post('/class-schedules', [AdminClassScheduleController::class, 'store'])->name('class-schedules.store');
    Route::get('/class-schedules/{classSchedule}', [AdminClassScheduleController::class, 'show'])->name('class-schedules.show');
    Route::get('/class-schedules/{classSchedule}/edit', [AdminClassScheduleController::class, 'edit'])->name('class-schedules.edit');
    Route::put('/class-schedules/{classSchedule}', [AdminClassScheduleController::class, 'update'])->name('class-schedules.update');
    Route::post('/class-schedules/{classSchedule}/deactivate', [AdminClassScheduleController::class, 'deactivate'])
        ->name('class-schedules.deactivate');
    Route::post('/class-schedules/{classSchedule}/reactivate', [AdminClassScheduleController::class, 'reactivate'])
        ->name('class-schedules.reactivate');

    Route::resource('enrollments', AdminEnrollmentController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/enrollments/{enrollment}/transfer', [AdminEnrollmentController::class, 'transfer'])
        ->name('enrollments.transfer');

    Route::get('/contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::patch('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'updateStatus'])->name('contact-messages.update');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    Route::prefix('ppdb/periods')->name('ppdb.periods.')->group(function (): void {
        Route::get('/', [AdminAdmissionPeriodController::class, 'index'])->name('index');
        Route::get('/create', [AdminAdmissionPeriodController::class, 'create'])->name('create');
        Route::post('/', [AdminAdmissionPeriodController::class, 'store'])->name('store');
        Route::get('/{admissionPeriod}', [AdminAdmissionPeriodController::class, 'show'])->name('show');
        Route::get('/{admissionPeriod}/edit', [AdminAdmissionPeriodController::class, 'edit'])->name('edit');
        Route::put('/{admissionPeriod}', [AdminAdmissionPeriodController::class, 'update'])->name('update');
    });
    Route::post('/ppdb/periods/{admissionPeriod}/open', [AdminAdmissionPeriodController::class, 'open'])->name('ppdb.periods.open');
    Route::post('/ppdb/periods/{admissionPeriod}/close', [AdminAdmissionPeriodController::class, 'close'])->name('ppdb.periods.close');
    Route::post('/ppdb/periods/{admissionPeriod}/archive', [AdminAdmissionPeriodController::class, 'archive'])->name('ppdb.periods.archive');

    Route::get('/ppdb/applications', [AdminAdmissionApplicationController::class, 'index'])->name('ppdb.applications.index');
    Route::get('/ppdb/review', [AdminAdmissionApplicationController::class, 'review'])->name('ppdb.review.index');
    Route::get('/ppdb/applications/{admissionApplication}', [AdminAdmissionApplicationController::class, 'show'])->name('ppdb.applications.show');
    Route::post('/ppdb/applications/{admissionApplication}/reviewer', [AdminAdmissionApplicationController::class, 'assign'])->name('ppdb.applications.reviewer.assign');
    Route::post('/ppdb/applications/{admissionApplication}/start-review', [AdminAdmissionApplicationController::class, 'start'])->name('ppdb.applications.start-review');
    Route::post('/ppdb/applications/{admissionApplication}/request-revision', [AdminAdmissionApplicationController::class, 'requestRevision'])->name('ppdb.applications.request-revision');
    Route::post('/ppdb/applications/{admissionApplication}/decide', [AdminAdmissionApplicationController::class, 'decide'])->name('ppdb.applications.decide');
    Route::post('/ppdb/applications/{admissionApplication}/convert', [AdminAdmissionApplicationController::class, 'convert'])->name('ppdb.applications.convert');

    Route::post('/ppdb/applications/{admissionApplication}/documents', [AdminAdmissionDocumentController::class, 'store'])->name('ppdb.applications.documents.store');
    Route::patch('/ppdb/documents/{document}', [AdminAdmissionDocumentController::class, 'updateStatus'])->name('ppdb.documents.update-status');
    Route::patch('/ppdb/documents/{document}/revision', [AdminAdmissionDocumentController::class, 'requestRevision'])->name('ppdb.documents.request-revision');
    Route::get('/ppdb/documents/{document}/download', [AdminAdmissionDocumentController::class, 'download'])->name('ppdb.documents.download');
    Route::post('/ppdb/applications/{admissionApplication}/announce', [AdminAdmissionApplicationController::class, 'announce'])
        ->name('ppdb.applications.announce');
});

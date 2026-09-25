<?php

use App\Http\Controllers\Admin\AchievementController as AdminAchievementController;
use App\Http\Controllers\Admin\AdmissionApplicationController as AdminAdmissionApplicationController;
use App\Http\Controllers\Admin\AdmissionDocumentController as AdminAdmissionDocumentController;
use App\Http\Controllers\Admin\AdmissionPeriodController as AdminAdmissionPeriodController;
use App\Http\Controllers\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Admin\GalleryAlbumController as AdminGalleryAlbumController;
use App\Http\Controllers\Admin\GalleryItemController as AdminGalleryItemController;
use App\Http\Controllers\Admin\NewsArticleController as AdminNewsArticleController;
use App\Http\Controllers\Admin\SchoolProfileController as AdminSchoolProfileController;
use App\Http\Controllers\Admin\TeacherDirectoryController as AdminTeacherDirectoryController;
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
use App\Http\Controllers\Public\AchievementController;
use App\Http\Controllers\Public\AdmissionController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\NewsArticleController;
use App\Http\Controllers\Public\SchoolProfileController;
use App\Http\Controllers\Public\TeacherDirectoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/health/ready', HealthController::class)->name('health.ready');
Route::get('/profil-sekolah', [SchoolProfileController::class, 'show'])->name('school-profile.show');
Route::get('/berita', [NewsArticleController::class, 'index'])->name('news.index');
Route::get('/berita/{slug}', [NewsArticleController::class, 'show'])->name('news.show');
Route::get('/prestasi', [AchievementController::class, 'index'])->name('achievements.index');
Route::get('/prestasi/{slug}', [AchievementController::class, 'show'])->name('achievements.show');
Route::get('/galeri', [GalleryController::class, 'index'])->name('gallery.index');
Route::get('/galeri/{slug}', [GalleryController::class, 'show'])->name('gallery.show');
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

Route::middleware(['auth', 'auth.session', 'active', 'verified', 'role:administrator,guru,siswa'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreference'])->name('notifications.preferences.update');
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

    Route::get('/teachers/public', [AdminTeacherDirectoryController::class, 'index'])->name('teachers.index');
    Route::get('/teachers/public/{teacher}/edit', [AdminTeacherDirectoryController::class, 'edit'])->name('teachers.edit');
    Route::put('/teachers/public/{teacher}', [AdminTeacherDirectoryController::class, 'update'])->name('teachers.update');
    Route::post('/teachers/public/{teacher}/publish', [AdminTeacherDirectoryController::class, 'publish'])->name('teachers.publish');
    Route::post('/teachers/public/{teacher}/unpublish', [AdminTeacherDirectoryController::class, 'unpublish'])->name('teachers.unpublish');

    Route::get('/contact-messages', [AdminContactMessageController::class, 'index'])->name('contact-messages.index');
    Route::get('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'show'])->name('contact-messages.show');
    Route::patch('/contact-messages/{contactMessage}', [AdminContactMessageController::class, 'updateStatus'])->name('contact-messages.update');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    Route::prefix('ppdb/periods')->name('ppdb.periods.')->group(function (): void {
        Route::get('/', [AdminAdmissionPeriodController::class, 'index'])->name('index');
        Route::get('/create', [AdminAdmissionPeriodController::class, 'create'])->name('create');
        Route::post('/', [AdminAdmissionPeriodController::class, 'store'])->name('store');
        Route::get('/{admissionPeriod}/edit', [AdminAdmissionPeriodController::class, 'edit'])->name('edit');
        Route::put('/{admissionPeriod}', [AdminAdmissionPeriodController::class, 'update'])->name('update');
    });
    Route::post('/ppdb/periods/{admissionPeriod}/open', [AdminAdmissionPeriodController::class, 'open'])->name('ppdb.periods.open');
    Route::post('/ppdb/periods/{admissionPeriod}/close', [AdminAdmissionPeriodController::class, 'close'])->name('ppdb.periods.close');
    Route::post('/ppdb/periods/{admissionPeriod}/archive', [AdminAdmissionPeriodController::class, 'archive'])->name('ppdb.periods.archive');

    Route::get('/ppdb/applications', [AdminAdmissionApplicationController::class, 'index'])->name('ppdb.applications.index');
    Route::get('/ppdb/applications/{admissionApplication}', [AdminAdmissionApplicationController::class, 'show'])->name('ppdb.applications.show');
    Route::post('/ppdb/applications/{admissionApplication}/reviewer', [AdminAdmissionApplicationController::class, 'assign'])->name('ppdb.applications.reviewer.assign');
    Route::post('/ppdb/applications/{admissionApplication}/start-review', [AdminAdmissionApplicationController::class, 'start'])->name('ppdb.applications.start-review');
    Route::post('/ppdb/applications/{admissionApplication}/request-revision', [AdminAdmissionApplicationController::class, 'requestRevision'])->name('ppdb.applications.request-revision');
    Route::post('/ppdb/applications/{admissionApplication}/decide', [AdminAdmissionApplicationController::class, 'decide'])->name('ppdb.applications.decide');
    Route::post('/ppdb/applications/{admissionApplication}/convert', [AdminAdmissionApplicationController::class, 'convert'])->name('ppdb.applications.convert');

    Route::post('/ppdb/applications/{admissionApplication}/documents', [AdminAdmissionDocumentController::class, 'store'])->name('ppdb.applications.documents.store');
    Route::patch('/ppdb/documents/{document}', [AdminAdmissionDocumentController::class, 'updateStatus'])->name('ppdb.documents.update-status');
    Route::get('/ppdb/documents/{document}/download', [AdminAdmissionDocumentController::class, 'download'])->name('ppdb.documents.download');
});

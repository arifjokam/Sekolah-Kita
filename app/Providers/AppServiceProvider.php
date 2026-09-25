<?php

namespace App\Providers;

use App\Models\AcademicYear;
use App\Models\Achievement;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use App\Models\AdmissionReview;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
use App\Models\ContactMessage;
use App\Models\Enrollment;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\Guardian;
use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\NotificationPreference;
use App\Models\SchoolProfile;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use App\Observers\TeacherObserver;
use App\Policies\AcademicYearPolicy;
use App\Policies\AchievementPolicy;
use App\Policies\AdmissionApplicationPolicy;
use App\Policies\AdmissionPeriodPolicy;
use App\Policies\AdmissionReviewPolicy;
use App\Policies\ApplicationDocumentPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ClassPolicy;
use App\Policies\ClassTeacherAssignmentPolicy;
use App\Policies\ContactMessagePolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\GalleryAlbumPolicy;
use App\Policies\GalleryItemPolicy;
use App\Policies\GuardianPolicy;
use App\Policies\MediaAssetPolicy;
use App\Policies\NewsArticlePolicy;
use App\Policies\NotificationPreferencePolicy;
use App\Policies\SchoolProfilePolicy;
use App\Policies\StudentPolicy;
use App\Policies\TeacherPolicy;
use App\Policies\TeachingAssignmentPolicy;
use App\Policies\TermPolicy;
use App\Policies\UserPolicy;
use App\View\Composers\AuthenticatedLayoutComposer;
use App\View\Composers\PublicSiteProfileComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(static fn (): Password => Password::min(12)->max(255));

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Teacher::class, TeacherPolicy::class);
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(Guardian::class, GuardianPolicy::class);
        Gate::policy(AcademicYear::class, AcademicYearPolicy::class);
        Gate::policy(Term::class, TermPolicy::class);
        Gate::policy(ClassModel::class, ClassPolicy::class);
        Gate::policy(ClassTeacherAssignment::class, ClassTeacherAssignmentPolicy::class);
        Gate::policy(TeachingAssignment::class, TeachingAssignmentPolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
        Gate::policy(SchoolProfile::class, SchoolProfilePolicy::class);
        Gate::policy(MediaAsset::class, MediaAssetPolicy::class);
        Gate::policy(NewsArticle::class, NewsArticlePolicy::class);
        Gate::policy(Achievement::class, AchievementPolicy::class);
        Gate::policy(GalleryAlbum::class, GalleryAlbumPolicy::class);
        Gate::policy(GalleryItem::class, GalleryItemPolicy::class);
        Gate::policy(ContactMessage::class, ContactMessagePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(NotificationPreference::class, NotificationPreferencePolicy::class);
        Gate::policy(AdmissionPeriod::class, AdmissionPeriodPolicy::class);
        Gate::policy(AdmissionApplication::class, AdmissionApplicationPolicy::class);
        Gate::policy(AdmissionReview::class, AdmissionReviewPolicy::class);
        Gate::policy(ApplicationDocument::class, ApplicationDocumentPolicy::class);

        Teacher::observe(TeacherObserver::class);

        Gate::define('manage-users', static fn (User $user): bool => $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator());
        Gate::define('access-administrator-area', static fn (User $user): bool => $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator());
        Gate::define('view-audit-log', static fn (User $user): bool => $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator());
        Gate::define('manage-notifications', static fn (User $user): bool => $user->isActive() && $user->hasVerifiedEmail());
        Gate::define('review-ppdb', static fn (User $user): bool => $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator());

        View::composer('layouts.public', PublicSiteProfileComposer::class);
        View::composer('layouts.app', AuthenticatedLayoutComposer::class);

        RateLimiter::for('login', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));
            $ipHash = hash('sha256', (string) $request->ip());
            $limits = [
                Limit::perMinute(15)->by('login-ip:'.$ipHash),
            ];

            if ($email !== '') {
                $emailHash = hash('sha256', $email);
                $pairHash = hash('sha256', $email.'|'.$ipHash);

                $limits[] = Limit::perMinute(5)->by('login-account:'.$emailHash);
                $limits[] = Limit::perMinute(10)->by('login-pair:'.$pairHash);
            }

            return $limits;
        });

        RateLimiter::for('password-reset', function (Request $request): array {
            $email = Str::lower(trim((string) $request->input('email')));
            $ipHash = hash('sha256', (string) $request->ip());
            $limits = [
                Limit::perMinute(10)->by('password-reset-ip:'.$ipHash),
            ];

            if ($email !== '') {
                $limits[] = Limit::perMinute(3)->by('password-reset-account:'.hash('sha256', $email));
            }

            return $limits;
        });

        RateLimiter::for('verification', function (Request $request): array {
            $userId = (string) ($request->user()?->getKey() ?? 'guest');
            $ipHash = hash('sha256', (string) $request->ip());

            return [
                Limit::perMinute(3)->by('verification-user:'.$userId),
                Limit::perMinute(10)->by('verification-ip:'.$ipHash),
            ];
        });

        RateLimiter::for('ppdb-submit', function (Request $request): array {
            $ipHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
            $sessionHash = hash_hmac('sha256', (string) $request->session()->getId(), (string) config('app.key'));
            $contact = Str::lower(trim((string) $request->input('contact_email')));
            $limits = [
                Limit::perHour(5)->by('ppdb-submit-ip:'.$ipHash),
                Limit::perDay(10)->by('ppdb-submit-session:'.$sessionHash),
            ];
            if ($contact !== '') {
                $limits[] = Limit::perDay(3)->by('ppdb-submit-contact:'.hash_hmac('sha256', $contact, (string) config('app.key')));
            }

            return $limits;
        });

        RateLimiter::for('ppdb-status', function (Request $request): array {
            $ipHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
            $tokenHash = hash_hmac('sha256', (string) $request->route('token'), (string) config('app.key'));

            return [
                Limit::perHour(60)->by('ppdb-status-ip:'.$ipHash),
                Limit::perHour(30)->by('ppdb-status-token:'.$tokenHash),
            ];
        });

        RateLimiter::for('ppdb-upload', function (Request $request): array {
            $ipHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
            $tokenHash = hash_hmac(
                'sha256',
                (string) $request->route('token').'|'.(string) $request->input('access_token'),
                (string) config('app.key'),
            );

            return [
                Limit::perDay(20)->by('ppdb-upload-ip:'.$ipHash),
                Limit::perDay(10)->by('ppdb-upload-token:'.$tokenHash),
            ];
        });

        RateLimiter::for('contact', function (Request $request): array {
            $ipHash = hash('sha256', (string) $request->ip());
            $contact = Str::lower(trim((string) ($request->input('email') ?: $request->input('phone'))));
            $limits = [
                Limit::perHour(20)->by('contact-ip:'.$ipHash),
            ];

            if ($contact !== '') {
                $limits[] = Limit::perHour(5)->by('contact-identity:'.hash('sha256', $contact.'|'.$ipHash));
            }

            return $limits;
        });
    }
}

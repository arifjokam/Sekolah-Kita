<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => AccountStatus::Inactive->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => AccountStatus::class,
        ];
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function assignedAdmissionApplications(): HasMany
    {
        return $this->hasMany(AdmissionApplication::class, 'assigned_reviewer_user_id');
    }

    public function admissionReviews(): HasMany
    {
        return $this->hasMany(AdmissionReview::class, 'reviewer_user_id');
    }

    public function profile(): Teacher|Student|null
    {
        return match ($this->role) {
            UserRole::Guru => $this->teacher,
            UserRole::Siswa => $this->student,
            default => null,
        };
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function hasAssignedRole(): bool
    {
        return $this->role instanceof UserRole;
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $role = $role instanceof UserRole ? $role : UserRole::tryFrom($role);

            if ($role !== null && $this->role === $role) {
                return true;
            }
        }

        return false;
    }

    public function isAdministrator(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    public function isGuru(): bool
    {
        return $this->role === UserRole::Guru;
    }

    public function isSiswa(): bool
    {
        return $this->role === UserRole::Siswa;
    }
}

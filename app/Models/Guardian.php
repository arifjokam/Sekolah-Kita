<?php

namespace App\Models;

use App\Enums\GuardianLifecycleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'legal_name',
    'phone',
    'email',
    'address',
    'national_id',
    'status',
])]
class Guardian extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'national_id',
        'address',
        'phone',
        'email',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => GuardianLifecycleStatus::class,
    ];

    public function studentGuardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardians')
            ->withPivot([
                'relationship_type',
                'is_primary_contact',
                'is_emergency_contact',
                'priority_order',
                'starts_on',
                'ends_on',
                'relationship_note',
            ])
            ->withTimestamps();
    }

    public function activeRelationships(): HasMany
    {
        $today = today();

        return $this->studentGuardians()
            ->where('starts_on', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('ends_on')
                    ->orWhere('ends_on', '>=', $today);
            });
    }

    public function isActive(): bool
    {
        return $this->status === GuardianLifecycleStatus::Active;
    }
}

<?php

namespace App\Models;

use App\Enums\AdmissionPeriodStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'academic_year_id',
    'name',
    'slug',
    'description',
    'instructions',
    'internal_notes',
    'opens_at',
    'closes_at',
    'status',
    'quota',
    'published_at',
])]
#[Hidden(['internal_notes'])]
class AdmissionPeriod extends Model
{
    use HasFactory;

    protected $casts = [
        'status' => AdmissionPeriodStatus::class,
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'published_at' => 'datetime',
        'quota' => 'integer',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(AdmissionApplication::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AdmissionPeriodStatus::Open->value,
            AdmissionPeriodStatus::Closed->value,
        ])->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AdmissionPeriodStatus::Open->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>=', now());
    }

    public function isCurrentlyOpen(): bool
    {
        return $this->status === AdmissionPeriodStatus::Open
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now())
            && $this->opens_at->lessThanOrEqualTo(now())
            && $this->closes_at->greaterThanOrEqualTo(now());
    }
}

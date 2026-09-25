<?php

namespace App\Models;

use App\Enums\GuardianRelationshipType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id',
    'guardian_id',
    'relationship_type',
    'is_primary_contact',
    'is_emergency_contact',
    'priority_order',
    'starts_on',
    'ends_on',
    'relationship_note',
])]
class StudentGuardian extends Model
{
    use HasFactory;

    protected $table = 'student_guardians';

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'relationship_note',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'relationship_type' => GuardianRelationshipType::class,
        'is_primary_contact' => 'boolean',
        'is_emergency_contact' => 'boolean',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function isActive(): bool
    {
        $today = today();

        return $this->starts_on !== null
            && $this->starts_on <= $today
            && ($this->ends_on === null || $this->ends_on >= $today);
    }
}

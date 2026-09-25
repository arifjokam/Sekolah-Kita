<?php

namespace App\Models;

use App\Enums\GuardianRelationshipType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admission_application_id',
    'canonical_guardian_id',
    'legal_name',
    'relationship_type',
    'phone',
    'email',
    'address',
    'is_primary_contact',
    'is_emergency_contact',
    'priority_order',
])]
#[Hidden(['legal_name', 'address', 'email', 'phone', 'canonical_guardian_id'])]
class ApplicationGuardian extends Model
{
    use HasFactory;

    protected $casts = [
        'relationship_type' => GuardianRelationshipType::class,
        'is_primary_contact' => 'boolean',
        'is_emergency_contact' => 'boolean',
        'priority_order' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function canonicalGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'canonical_guardian_id');
    }
}

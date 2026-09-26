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
    'national_id',
    'relationship_type',
    'phone',
    'whatsapp_number',
    'email',
    'occupation',
    'address',
    'is_primary_contact',
    'is_emergency_contact',
    'priority_order',
])]
#[Hidden(['legal_name', 'national_id', 'address', 'email', 'phone', 'whatsapp_number', 'occupation', 'canonical_guardian_id'])]
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

    /**
     * The number a WhatsApp message should go to, falling back to the plain phone
     * number when no dedicated WhatsApp number was given.
     */
    public function whatsappTarget(): ?string
    {
        return $this->whatsapp_number !== null && $this->whatsapp_number !== ''
            ? $this->whatsapp_number
            : $this->phone;
    }
}

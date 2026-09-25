<?php

namespace App\Models;

use App\Enums\AdmissionActorType;
use App\Enums\AdmissionApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admission_application_id',
    'from_status',
    'to_status',
    'actor_type',
    'actor_user_id',
    'reason_code',
    'public_message',
    'correlation_id',
    'workflow_version',
    'occurred_at',
])]
#[Hidden(['correlation_id'])]
class ApplicationStatusHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Application status history is append-only.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Application status history is append-only.');
        });
    }

    protected $casts = [
        'from_status' => AdmissionApplicationStatus::class,
        'to_status' => AdmissionApplicationStatus::class,
        'actor_type' => AdmissionActorType::class,
        'workflow_version' => 'integer',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

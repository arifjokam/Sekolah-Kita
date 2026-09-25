<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'actor_user_id',
    'action',
    'result',
    'target_type',
    'target_id',
    'changed_fields',
    'reason_code',
    'correlation_id',
    'ip_hash',
    'user_agent_hash',
    'occurred_at',
])]
#[Hidden([
    'ip_hash',
    'user_agent_hash',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Audit records are append-only.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Audit records are append-only.');
        });
    }

    protected $casts = [
        'changed_fields' => 'array',
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

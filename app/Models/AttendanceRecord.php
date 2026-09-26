<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's attendance for one session.
 *
 * The student is a foreign key and never a copied name, so a transfer or a legal
 * name change cannot leave a stale label behind in a permanent record.
 */
#[Fillable([
    'attendance_session_id',
    'student_id',
    'status',
    'note',
    'marked_by_user_id',
    'marked_at',
    'revision',
])]
class AttendanceRecord extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => AttendanceStatus::class,
        'marked_at' => 'datetime',
        'revision' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_user_id');
    }
}

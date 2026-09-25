<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'email',
    'phone',
    'subject',
    'message',
    'status',
    'responded_at',
])]
#[Hidden(['email', 'phone', 'message'])]
class ContactMessage extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ContactMessageStatus::class,
        'responded_at' => 'datetime',
    ];
}

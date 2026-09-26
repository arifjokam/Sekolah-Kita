<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The authenticated notification list is
 *
 *   SELECT * FROM notifications
 *   WHERE notifiable_type = ? AND notifiable_id = ?
 *   ORDER BY created_at DESC
 *   LIMIT 20
 *
 * The existing recipient indexes lead with (notifiable_type, notifiable_id)
 * but do not include created_at, so MariaDB has no index that can satisfy the
 * newest-first ordering. EXPLAIN reports "Using filesort", meaning every
 * /notifications request sorts the user's entire notification history, and the
 * cost grows with the account's notification count rather than with the page
 * size.
 *
 * Extending the recipient index with created_at lets the same query be answered
 * in index order, reading only the rows it returns. The index is additive and
 * does not modify or remove existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('notifications', 'notifications_recipient_timeline_index')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->index(
                    ['notifiable_type', 'notifiable_id', 'created_at'],
                    'notifications_recipient_timeline_index',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('notifications', 'notifications_recipient_timeline_index')) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropIndex('notifications_recipient_timeline_index');
            });
        }
    }
};

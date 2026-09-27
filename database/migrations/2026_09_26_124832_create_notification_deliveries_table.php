<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable delivery state for out-of-band notification channels.
 *
 * The in-app (database) channel needs no table: Laravel's notifications table
 * already is that record. What was missing was any way to answer "was the
 * WhatsApp actually delivered, attempted, skipped or impossible?", so a
 * not-configured provider could never be distinguished from a silent success.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 30);
            $table->string('provider', 60);
            $table->string('notification_class', 255);
            $table->nullableMorphs('notifiable');
            $table->string('recipient', 120);
            $table->string('status', 30)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('body')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status', 'created_at'], 'notification_deliveries_status_index');
            $table->index(
                ['notifiable_type', 'notifiable_id', 'channel', 'created_at'],
                'notification_deliveries_recipient_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};

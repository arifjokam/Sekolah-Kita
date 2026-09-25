<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('category', 50);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['user_id', 'channel', 'category'],
                'notification_preferences_user_channel_category_unique',
            );
            $table->index(['user_id', 'category'], 'notification_preferences_user_category_index');
        });
    }

    public function down(): void
    {
        // Notification preferences are retained with the user lifecycle.
    }
};

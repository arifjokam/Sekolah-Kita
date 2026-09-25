<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('subject', 200);
            $table->text('message');
            $table->string('status', 20)->default('new');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(
                ['status', 'created_at', 'id'],
                'contact_messages_moderation_index',
            );
        });
    }

    /**
     * Forward-only additive migration; no destructive rollback is provided.
     */
    public function down(): void
    {
        //
    }
};

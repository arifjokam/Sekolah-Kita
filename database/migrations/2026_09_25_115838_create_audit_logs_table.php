<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 120);
            $table->string('result', 20)->default('success');
            $table->string('target_type', 100)->nullable();
            $table->string('target_id', 100)->nullable();
            $table->json('changed_fields')->nullable();
            $table->string('reason_code', 100)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_user_id', 'occurred_at'], 'audit_logs_actor_time_index');
            $table->index(['target_type', 'target_id', 'occurred_at'], 'audit_logs_target_time_index');
            $table->index(['action', 'occurred_at'], 'audit_logs_action_time_index');
        });
    }

    public function down(): void
    {
        // Audit records are append-only and are not removed by ordinary rollback.
    }
};

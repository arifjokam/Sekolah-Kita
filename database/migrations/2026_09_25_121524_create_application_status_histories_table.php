<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('actor_type', 20);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reason_code', 100)->nullable();
            $table->string('public_message', 255)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->unsignedInteger('workflow_version');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['admission_application_id', 'workflow_version'], 'application_status_histories_application_version_unique');
            $table->index(['admission_application_id', 'occurred_at', 'id'], 'application_status_histories_timeline_index');
            $table->index(['actor_type', 'occurred_at'], 'application_status_histories_actor_time_index');
        });
    }

    public function down(): void
    {
        // Status history is immutable workflow evidence.
    }
};

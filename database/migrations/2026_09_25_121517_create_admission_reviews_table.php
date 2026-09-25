<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->restrictOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 30)->default('decision');
            $table->string('decision', 30)->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['admission_application_id', 'created_at', 'id'], 'admission_reviews_application_time_index');
            $table->index(['reviewer_user_id', 'reviewed_at', 'id'], 'admission_reviews_reviewer_time_index');
        });
    }

    public function down(): void
    {
        // Review history is append-oriented and retained.
    }
};

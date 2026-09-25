<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_period_id')->constrained('admission_periods')->restrictOnDelete();
            $table->foreignId('assigned_reviewer_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('target_class_id')->nullable()->constrained('classes')->restrictOnDelete();
            $table->foreignId('converted_student_id')->nullable()->unique()->constrained('students')->restrictOnDelete();
            $table->foreignId('converted_enrollment_id')->nullable()->unique()->constrained('enrollments')->restrictOnDelete();
            $table->string('registration_number', 40)->unique();
            $table->string('applicant_name', 150);
            $table->string('preferred_name', 150)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('contact_email', 150);
            $table->string('contact_phone', 30);
            $table->text('address')->nullable();
            $table->string('current_school', 150)->nullable();
            $table->unsignedSmallInteger('requested_grade_level')->nullable();
            $table->string('source', 40)->default('public_web');
            $table->string('status', 30)->default('submitted');
            $table->unsignedInteger('workflow_version')->default(1);
            $table->string('conversion_status', 20)->default('not_started');
            $table->string('consent_version', 30);
            $table->timestamp('consent_accepted_at');
            $table->timestamp('contact_verified_at')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('revision_requested_at')->nullable();
            $table->timestamp('revision_deadline')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('conversion_error_code', 80)->nullable();
            $table->char('duplicate_fingerprint', 64)->nullable();
            $table->boolean('duplicate_flag')->default(false);
            $table->timestamp('duplicate_reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['admission_period_id', 'status', 'submitted_at', 'id'], 'admission_applications_period_queue_index');
            $table->index(['assigned_reviewer_user_id', 'status', 'submitted_at', 'id'], 'admission_applications_reviewer_queue_index');
            $table->index(['status', 'created_at', 'id'], 'admission_applications_status_time_index');
            $table->index(['duplicate_flag', 'duplicate_reviewed_at'], 'admission_applications_duplicate_review_index');
        });
    }

    public function down(): void
    {
        // Applications retain workflow history and are not dropped by rollback.
    }
};

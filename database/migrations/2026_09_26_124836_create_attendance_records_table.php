<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One attendance record per enrolled student per session.
 *
 * The student is referenced, never copied: a record must never carry a free-text
 * student name, because a name is not an identity and would survive a transfer.
 * The unique (session, student) pair is the real anti-duplicate guarantee, so a
 * double submit cannot produce two rows for the same lesson.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('present');
            $table->string('note', 255)->nullable();
            $table->foreignId('marked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marked_at')->nullable();
            $table->unsignedSmallInteger('revision')->default(0);
            $table->timestamps();

            $table->unique(['attendance_session_id', 'student_id'], 'attendance_records_session_student_unique');

            // A student's own attendance summary, and the monthly report, both
            // aggregate by student inside a date range.
            $table->index(['student_id', 'attendance_session_id'], 'attendance_records_student_session_index');
            $table->index(['status', 'attendance_session_id'], 'attendance_records_status_session_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};

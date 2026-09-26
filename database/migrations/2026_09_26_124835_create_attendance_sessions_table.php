<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One occurrence of a scheduled lesson, on a date, for a class.
 *
 * The schedule repeats weekly, so it cannot itself hold attendance: a row here
 * is the concrete "Monday 07:00–08:30 7A Matematika with Mr Adi" the teacher
 * actually marked. It keeps its own academic year, term, subject and teacher
 * snapshot so a later schedule edit cannot rewrite historical attendance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->date('session_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('status', 20)->default('open');
            $table->text('note')->nullable();
            $table->foreignId('marked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            /*
             * The core anti-duplicate rule: a scheduled lesson can only produce
             * one session per date. The schedule id is nullable for an ad-hoc
             * session, and MariaDB treats NULLs as distinct in a unique index, so
             * ad-hoc sessions are free while scheduled ones are not.
             */
            $table->unique(['schedule_id', 'session_date'], 'attendance_sessions_schedule_date_unique');

            // "Today's lessons for me" and the monthly report both read by date
            // inside a class, so date leads and the class is the second column.
            $table->index(['teacher_id', 'session_date'], 'attendance_sessions_teacher_date_index');
            $table->index(['class_id', 'session_date'], 'attendance_sessions_class_date_index');
            $table->index(['academic_year_id', 'term_id', 'session_date'], 'attendance_sessions_scope_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The weekly class schedule.
 *
 * A schedule row is one recurring weekly lesson: academic year, semester, class,
 * subject, teacher, day, start/end time and an optional room. It hangs off the
 * existing academic masters rather than duplicating them, so a lesson can never
 * reference a term from a different academic year than its class — that is
 * enforced in ScheduleAdministrationService, which the request also calls.
 *
 * Times are stored as TIME because the week repeats: there is no date component
 * to store. The concrete occurrence of a lesson on a given day is an attendance
 * session, which is a separate table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room', 50)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            // Overlap detection reads "active lessons for this class on this day,
            // ordered by start time" and "…for this teacher on this day".
            $table->index(['class_id', 'day_of_week', 'starts_at'], 'class_schedules_class_day_index');
            $table->index(['teacher_id', 'day_of_week', 'starts_at'], 'class_schedules_teacher_day_index');
            $table->index(['term_id', 'class_id', 'day_of_week'], 'class_schedules_term_class_day_index');

            // The list screen filters by academic year and term before anything
            // else, and the room check is only meaningful for a room that exists.
            $table->index(['academic_year_id', 'term_id', 'status'], 'class_schedules_scope_index');
            $table->index(['room', 'day_of_week', 'starts_at'], 'class_schedules_room_day_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedules');
    }
};

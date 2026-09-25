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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->date('enrolled_on');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->date('completed_on')->nullable();
            $table->text('transition_reason')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'academic_year_id', 'status']);
            $table->index(['class_id', 'status', 'student_id']);
            $table->index(['academic_year_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};

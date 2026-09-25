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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('grade_level');
            $table->string('name', 50);
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 20)->default('planned');
            $table->date('closed_at')->nullable();
            $table->text('closure_reason')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'grade_level', 'name']);
            $table->index(['academic_year_id', 'status', 'grade_level', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};

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
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('legal_name', 150);
            $table->string('employee_number', 50)->unique();
            $table->string('employment_status', 20)->default('active');
            $table->date('employment_start_date');
            $table->date('employment_end_date')->nullable();
            $table->string('preferred_name', 150)->nullable();
            $table->string('position_title', 100)->nullable();
            $table->text('qualification_summary')->nullable();
            $table->text('biography')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->timestamps();

            $table->index(['employment_status', 'legal_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};

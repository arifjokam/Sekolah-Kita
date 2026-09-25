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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('legal_name', 150);
            $table->string('student_number', 50)->unique();
            $table->string('status', 20)->default('active');
            $table->date('admission_date');
            $table->date('graduation_date')->nullable();
            $table->date('leaving_date')->nullable();
            $table->text('leaving_reason')->nullable();
            $table->string('preferred_name', 150)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('nisn', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->timestamps();

            $table->index(['status', 'student_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

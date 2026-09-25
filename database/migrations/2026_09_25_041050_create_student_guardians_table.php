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
        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->string('relationship_type', 30);
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->unsignedSmallInteger('priority_order')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('relationship_note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'ends_on', 'is_primary_contact', 'priority_order'], 'sg_student_active_idx');
            $table->index(['guardian_id', 'ends_on'], 'sg_guardian_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
    }
};

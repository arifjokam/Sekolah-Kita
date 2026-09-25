<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('quota')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'opens_at', 'closes_at'], 'admission_periods_public_window_index');
            $table->index(['academic_year_id', 'status'], 'admission_periods_year_status_index');
        });
    }

    public function down(): void
    {
        // Admission periods are archived rather than dropped by rollback.
    }
};

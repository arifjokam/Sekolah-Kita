<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->restrictOnDelete();
            $table->foreignId('canonical_guardian_id')->nullable()->constrained('guardians')->restrictOnDelete();
            $table->string('legal_name', 150);
            $table->string('relationship_type', 30);
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_primary_contact')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->unsignedSmallInteger('priority_order')->nullable();
            $table->timestamps();

            $table->index(['admission_application_id', 'is_primary_contact', 'priority_order'], 'application_guardians_primary_index');
            $table->index(['canonical_guardian_id'], 'application_guardians_canonical_index');
        });
    }

    public function down(): void
    {
        // Application guardian data follows the application retention policy.
    }
};

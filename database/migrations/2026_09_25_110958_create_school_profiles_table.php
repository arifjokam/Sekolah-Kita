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
        Schema::create('school_profiles', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_singleton')->default(true)->unique();
            $table->string('legal_name', 150);
            $table->string('public_name', 150)->nullable();
            $table->string('short_name', 50)->nullable();
            $table->string('address_line', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('postal_code', 10)->nullable();
            $table->string('public_phone', 30)->nullable();
            $table->string('public_email', 150)->nullable();
            $table->text('vision')->nullable();
            $table->text('mission')->nullable();
            $table->text('about')->nullable();
            $table->foreignId('logo_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->foreignId('hero_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Forward-only additive migration; no destructive rollback is provided.
     */
    public function down(): void
    {
        //
    }
};

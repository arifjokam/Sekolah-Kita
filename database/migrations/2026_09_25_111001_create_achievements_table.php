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
        Schema::create('achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->text('description');
            $table->string('category', 100);
            $table->string('level', 100);
            $table->date('achievement_date');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(
                ['status', 'achievement_date', 'id'],
                'achievements_public_listing_index',
            );
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

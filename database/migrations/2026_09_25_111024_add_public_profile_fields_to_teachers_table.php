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
        Schema::table('teachers', function (Blueprint $table): void {
            $table->foreignId('photo_media_id')->nullable()->after('biography')->constrained('media_assets')->restrictOnDelete();
            $table->string('directory_slug', 180)->nullable()->unique()->after('photo_media_id');
            $table->boolean('is_publicly_visible')->default(false)->after('directory_slug');
            $table->timestamp('published_at')->nullable()->after('is_publicly_visible');

            $table->index(
                ['is_publicly_visible', 'employment_status', 'published_at', 'id'],
                'teachers_public_directory_index',
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

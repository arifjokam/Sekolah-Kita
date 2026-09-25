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
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('uploader_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('private_disk', 50);
            $table->string('private_object_key', 255);
            $table->string('public_disk', 50)->nullable();
            $table->string('public_object_key', 255)->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('processing_status', 20)->default('ready');
            $table->string('visibility', 20)->default('private');
            $table->timestamp('metadata_sanitized_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['private_disk', 'private_object_key'],
                'media_assets_private_object_unique',
            );
            $table->index(
                ['visibility', 'processing_status'],
                'media_assets_visibility_processing_index',
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

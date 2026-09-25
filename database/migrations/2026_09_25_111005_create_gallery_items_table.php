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
        Schema::create('gallery_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gallery_album_id')->constrained('gallery_albums')->restrictOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->string('caption', 255)->nullable();
            $table->string('alt_text', 255);
            $table->date('event_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['gallery_album_id', 'status', 'sort_order', 'id'],
                'gallery_items_public_listing_index',
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

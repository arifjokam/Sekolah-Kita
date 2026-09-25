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
        Schema::create('gallery_albums', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['status', 'sort_order', 'id'],
                'gallery_albums_public_listing_index',
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

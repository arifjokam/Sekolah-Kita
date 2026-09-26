<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records the media assets embedded in a news article body.
     *
     * The article body is a single HTML column, so the images inside it would
     * otherwise have no referential link to media_assets. Without this table
     * MediaAssetService has no way to know that a media asset is still in use,
     * which would let it withdraw a public derivative that a published article
     * still points at. A row therefore means "this image is referenced by this
     * article body".
     *
     * news_article_id is nullable for one reason only: the admin create screen
     * uploads images before the article row exists. Those staged rows are
     * adopted by the article on first save and pruned by
     * news:prune-staged-article-images if the draft is abandoned.
     */
    public function up(): void
    {
        Schema::create('news_article_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_article_id')->nullable()->constrained('news_articles')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->timestamps();

            $table->index(
                ['news_article_id', 'media_asset_id'],
                'news_article_media_article_media_index',
            );
            $table->index(
                ['media_asset_id'],
                'news_article_media_media_index',
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

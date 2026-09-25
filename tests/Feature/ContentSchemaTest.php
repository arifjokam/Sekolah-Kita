<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContentSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_content_tables_and_columns_exist(): void
    {
        foreach (['school_profiles', 'media_assets', 'news_articles', 'achievements', 'gallery_albums', 'gallery_items', 'contact_messages'] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table.' table is missing');
        }

        $this->assertTrue(Schema::hasColumns('media_assets', ['private_disk', 'private_object_key', 'public_disk', 'public_object_key', 'processing_status', 'visibility', 'metadata_sanitized_at']));
        $this->assertTrue(Schema::hasColumns('news_articles', ['author_user_id', 'cover_media_id', 'title', 'slug', 'excerpt', 'body', 'status', 'published_at']));
        $this->assertTrue(Schema::hasColumns('achievements', ['title', 'slug', 'description', 'category', 'level', 'achievement_date', 'status', 'published_at']));
        $this->assertTrue(Schema::hasColumns('gallery_items', ['gallery_album_id', 'media_asset_id', 'caption', 'alt_text', 'event_date', 'status', 'published_at']));
        $this->assertTrue(Schema::hasColumns('contact_messages', ['name', 'email', 'phone', 'subject', 'message', 'status', 'responded_at']));
        $this->assertTrue(Schema::hasColumns('teachers', ['photo_media_id', 'directory_slug', 'is_publicly_visible', 'published_at']));
    }

    public function test_single_school_and_public_content_constraints_are_present_on_mariadb(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MariaDB-specific index test');
        }

        $indexes = DB::select('SHOW INDEX FROM school_profiles WHERE Key_name = "school_profiles_is_singleton_unique"');
        $this->assertNotEmpty($indexes);

        foreach (['news_articles_slug_unique', 'achievements_slug_unique', 'gallery_albums_slug_unique', 'teachers_directory_slug_unique'] as $index) {
            $this->assertNotEmpty(DB::select('SHOW INDEX FROM '.match ($index) {
                'news_articles_slug_unique' => 'news_articles',
                'achievements_slug_unique' => 'achievements',
                'gallery_albums_slug_unique' => 'gallery_albums',
                default => 'teachers',
            }.' WHERE Key_name = "'.$index.'"'));
        }
    }

    public function test_public_read_and_moderation_indexes_exist_on_mariadb(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MariaDB-specific index test');
        }

        foreach ([
            'media_assets_visibility_processing_index' => 'media_assets',
            'news_articles_public_listing_index' => 'news_articles',
            'achievements_public_listing_index' => 'achievements',
            'gallery_albums_public_listing_index' => 'gallery_albums',
            'gallery_items_public_listing_index' => 'gallery_items',
            'contact_messages_moderation_index' => 'contact_messages',
            'teachers_public_directory_index' => 'teachers',
        ] as $index => $table) {
            $this->assertNotEmpty(DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = "'.$index.'"'));
        }
    }

    public function test_media_and_content_foreign_keys_are_restrictive_and_no_student_recipient_table_exists(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('MariaDB-specific foreign key test');
        }

        $foreignKeys = DB::select('SELECT CONSTRAINT_NAME, DELETE_RULE FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE()');
        $rules = collect($foreignKeys)->keyBy('CONSTRAINT_NAME');

        foreach ([
            'news_articles_author_user_id_foreign',
            'news_articles_cover_media_id_foreign',
            'achievements_cover_media_id_foreign',
            'gallery_items_gallery_album_id_foreign',
            'gallery_items_media_asset_id_foreign',
            'teachers_photo_media_id_foreign',
            'school_profiles_logo_media_id_foreign',
            'school_profiles_hero_media_id_foreign',
        ] as $constraint) {
            $this->assertTrue($rules->has($constraint), $constraint.' is missing');
            $this->assertSame('RESTRICT', $rules->get($constraint)->DELETE_RULE);
        }

        $this->assertFalse(Schema::hasTable('achievement_recipients'));
        $this->assertFalse(Schema::hasColumn('achievements', 'student_id'));
        $this->assertFalse(Schema::hasColumn('news_articles', 'student_id'));
        $this->assertFalse(Schema::hasColumn('gallery_items', 'student_id'));
        $this->assertFalse(Schema::hasColumn('teachers', 'school_id'));
    }
}

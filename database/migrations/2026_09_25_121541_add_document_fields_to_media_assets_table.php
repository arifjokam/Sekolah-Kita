<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->unsignedSmallInteger('width')->nullable()->change();
            $table->unsignedSmallInteger('height')->nullable()->change();
            $table->string('media_kind', 20)->default('image')->after('uploader_user_id');
            $table->string('original_filename', 255)->nullable()->after('media_kind');
            $table->string('scan_status', 20)->default('ready')->after('processing_status');
            $table->timestamp('scanned_at')->nullable()->after('scan_status');
            $table->string('scan_error_code', 80)->nullable()->after('scanned_at');
        });
    }

    public function down(): void
    {
        // Media metadata is retained for audit and retention workflows.
    }
};

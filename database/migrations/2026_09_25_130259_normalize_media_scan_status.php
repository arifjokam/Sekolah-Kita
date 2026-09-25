<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('media_assets')->where('scan_status', 'ready')->update(['scan_status' => 'clean']);

        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('scan_status', 20)->default('clean')->change();
        });
    }

    public function down(): void
    {
        // Scan status normalization is not reversed because it would misstate scan evidence.
    }
};

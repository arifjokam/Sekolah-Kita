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
        if (! Schema::hasIndex('audit_logs', 'audit_logs_occurred_at_index')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->index('occurred_at', 'audit_logs_occurred_at_index');
            });
        }

        if (! Schema::hasIndex('admission_applications', 'admission_applications_duplicate_lookup_index')) {
            Schema::table('admission_applications', function (Blueprint $table): void {
                $table->index(
                    ['admission_period_id', 'duplicate_fingerprint'],
                    'admission_applications_duplicate_lookup_index',
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('admission_applications', 'admission_applications_duplicate_lookup_index')) {
            Schema::table('admission_applications', function (Blueprint $table): void {
                $table->dropIndex('admission_applications_duplicate_lookup_index');
            });
        }

        if (Schema::hasIndex('audit_logs', 'audit_logs_occurred_at_index')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->dropIndex('audit_logs_occurred_at_index');
            });
        }
    }
};

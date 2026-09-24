<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the fixed role and account status fields to the existing users table.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32)->nullable()->after('email_verified_at');
            $table->string('status', 20)->default('inactive')->after('role');
            $table->index('status', 'users_status_index');
            $table->index(['role', 'status'], 'users_role_status_index');
        });
    }

    /**
     * Reverse only this additive identity change during local/test teardown.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_role_status_index');
            $table->dropIndex('users_status_index');
            $table->dropColumn(['role', 'status']);
        });
    }
};

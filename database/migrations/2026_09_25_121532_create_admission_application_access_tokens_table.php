<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_application_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_application_id')
                ->constrained('admission_applications', indexName: 'admission_access_tokens_application_foreign')
                ->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('purpose', 40);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['admission_application_id', 'purpose', 'expires_at'], 'admission_access_tokens_application_purpose_index');
            $table->index(['expires_at', 'revoked_at'], 'admission_access_tokens_expiry_index');
        });
    }

    public function down(): void
    {
        // Access tokens are short-lived credentials and are not retained by rollback.
    }
};

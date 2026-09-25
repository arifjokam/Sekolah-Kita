<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->restrictOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->restrictOnDelete();
            $table->string('document_type', 40);
            $table->unsignedSmallInteger('version')->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('verification_status', 20)->default('pending');
            $table->text('reviewer_note')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['admission_application_id', 'document_type', 'version'], 'application_documents_type_version_unique');
            $table->index(['admission_application_id', 'verification_status'], 'application_documents_review_index');
            $table->index(['media_asset_id'], 'application_documents_media_index');
        });
    }

    public function down(): void
    {
        // Document records are retained and never removed by ordinary rollback.
    }
};

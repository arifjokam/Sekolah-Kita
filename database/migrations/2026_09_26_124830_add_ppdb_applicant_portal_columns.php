<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PPDB applicant portal: link an application to a portal account, widen the
 * biodata the portal collects, and give guardians the fields a school actually
 * asks a parent for.
 *
 * Forward-only and additive. Nothing is dropped and nothing is renamed, so the
 * existing public one-shot PPDB form, its access tokens and every historical
 * application row keep working exactly as before. Every new column is nullable:
 * an application created through the public form simply leaves it empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table): void {
            // The portal account that owns this application. Null for every
            // application created through the public form, which is what keeps
            // the two flows independent.
            $table->foreignId('applicant_user_id')
                ->nullable()
                ->after('admission_period_id')
                ->constrained('users')
                ->restrictOnDelete();

            // Biodata the portal collects progressively. The public form only
            // ever writes the columns it already wrote, so it is unaffected.
            $table->string('national_id', 32)->nullable()->after('preferred_name');
            $table->string('nisn', 20)->nullable()->after('national_id');
            $table->string('address_rt', 10)->nullable()->after('address');
            $table->string('address_rw', 10)->nullable()->after('address_rt');
            $table->string('village', 120)->nullable()->after('address_rw');
            $table->string('district', 120)->nullable()->after('village');
            $table->string('regency', 120)->nullable()->after('district');
            $table->string('province', 120)->nullable()->after('regency');
            $table->string('postal_code', 10)->nullable()->after('province');
            $table->unsignedSmallInteger('graduation_year')->nullable()->after('current_school');

            // Normalised WhatsApp number, stored separately from contact_phone
            // because a school may want a different number for messaging.
            $table->string('contact_whatsapp', 30)->nullable()->after('contact_phone');

            // Set when the application leaves the applicant's editable state, so
            // the portal can tell "locked because submitted" from "locked because
            // the period is closed" without re-deriving it.
            $table->timestamp('locked_at')->nullable()->after('submitted_at');

            // Index shaped for the two hot lookups: "the portal account's own
            // applications, newest first" and "unlocked drafts for an account".
            $table->index(['applicant_user_id', 'status', 'id'], 'admission_applications_portal_account_index');
            $table->index(['applicant_user_id', 'admission_period_id'], 'admission_applications_portal_period_index');
        });

        Schema::table('application_guardians', function (Blueprint $table): void {
            $table->string('national_id', 32)->nullable()->after('legal_name');
            $table->string('occupation', 100)->nullable()->after('email');
            $table->string('whatsapp_number', 30)->nullable()->after('phone');

            $table->index(['admission_application_id', 'relationship_type'], 'application_guardians_relationship_index');
        });

        Schema::table('admission_reviews', function (Blueprint $table): void {
            /*
             * Document-level review events reuse the existing review trail
             * instead of introducing a parallel one. Null for the application
             * level events that already live here.
             */
            $table->foreignId('application_document_id')
                ->nullable()
                ->after('admission_application_id')
                ->constrained('application_documents')
                ->restrictOnDelete();

            $table->index(['application_document_id', 'created_at'], 'admission_reviews_document_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::table('admission_reviews', function (Blueprint $table): void {
            $table->dropIndex('admission_reviews_document_timeline_index');
            $table->dropConstrainedForeignId('application_document_id');
        });

        Schema::table('application_guardians', function (Blueprint $table): void {
            $table->dropIndex('application_guardians_relationship_index');
            $table->dropColumn(['national_id', 'occupation', 'whatsapp_number']);
        });

        Schema::table('admission_applications', function (Blueprint $table): void {
            $table->dropIndex('admission_applications_portal_period_index');
            $table->dropIndex('admission_applications_portal_account_index');
            $table->dropConstrainedForeignId('applicant_user_id');
            $table->dropColumn([
                'national_id',
                'nisn',
                'address_rt',
                'address_rw',
                'village',
                'district',
                'regency',
                'province',
                'postal_code',
                'graduation_year',
                'contact_whatsapp',
                'locked_at',
            ]);
        });
    }
};

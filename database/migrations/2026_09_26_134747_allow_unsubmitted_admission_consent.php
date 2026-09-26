<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A draft application has not been submitted, consented, or decided yet.
 *
 * The admission_applications schema was written when every application was born
 * submitted from the public one-shot form, so three columns were NOT NULL with no
 * default:
 *
 *   - consent_version, consent_accepted_at
 *   - submitted_at
 *
 * The applicant portal makes an unsent draft a first-class state: a candidate
 * creates an application and fills it in over several visits. Without this
 * migration a draft row cannot physically exist, because the database insists it
 * was already submitted and already consented.
 *
 * All three become nullable. Nothing is dropped, nothing is back-filled, and
 * every existing row keeps the values it already has: the public one-shot form has
 * always written all three together, so no historical record changes meaning.
 * status is the authoritative signal for "has this been submitted" — a draft
 * carries status = 'draft' and a NULL submitted_at, and the two cannot disagree
 * because AdmissionApplicationService is the only other writer and always sets
 * both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_applications', function (Blueprint $table): void {
            $table->string('consent_version', 30)->nullable()->change();
            $table->timestamp('consent_accepted_at')->nullable()->change();
            $table->timestamp('submitted_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Reversing would need a value for every draft row, and inventing one
        // would be exactly the fiction this migration removes. All three columns
        // stay nullable.
    }
};

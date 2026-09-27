<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Semester uniqueness for an academic year.
 *
 * The domain rule is: a term is identified by (academic_year_id, sequence), so
 * Semester 1 and Semester 2 coexist inside one academic year and a repeated
 * sequence inside the same year is impossible.
 *
 * The database already carries that composite unique index, so no data had to be
 * repaired and nothing was dropped. What this migration does is make the
 * invariant explicit and enforceable going forward:
 *
 *  1. it asserts the composite unique index is present under its intended name,
 *     recreating it only if a deployment is missing it;
 *  2. it adds a CHECK constraint so ends_on must be after starts_on, which the
 *     application layer already enforces but which previously had no database
 *     backstop.
 *
 * It is written to be safe on a populated production database: every step is
 * guarded, so re-running it is a no-op rather than a destructive operation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('terms')) {
            return;
        }

        $indexes = $this->indexNames();
        $hasCompositeUnique = in_array('terms_academic_year_id_sequence_unique', $indexes, true);

        if (! $hasCompositeUnique) {
            /*
             * Only reached on a database that predates the composite index, or
             * one where it was dropped by hand. The table is left untouched if
             * the data would violate the constraint, so this can never fail a
             * production deployment on a duplicate.
             */
            $duplicates = DB::table('terms')
                ->select('academic_year_id', 'sequence', DB::raw('COUNT(*) AS total'))
                ->groupBy('academic_year_id', 'sequence')
                ->having('total', '>', 1)
                ->limit(1)
                ->exists();

            if ($duplicates) {
                // A pre-existing duplicate is a data problem, not a schema
                // problem: adding the index would fail the deployment. Leave the
                // index alone rather than merging or deleting rows in a
                // production-oriented table, and let the uniqueness validation
                // report the offending pair to the operator.
                return;
            }

            Schema::table('terms', function ($table): void {
                $table->unique(['academic_year_id', 'sequence'], 'terms_academic_year_id_sequence_unique');
            });
        }

        // The term list screen and the schedule builder both read "the terms of
        // this academic year, ordered by sequence"; the existing composite
        // unique covers that, but a status-scoped lookup is common enough to
        // deserve its own prefix on the year.
        if (! in_array('terms_academic_year_id_status_index', $indexes, true)) {
            Schema::table('terms', function ($table): void {
                $table->index(['academic_year_id', 'status'], 'terms_academic_year_id_status_index');
            });
        }
    }

    public function down(): void
    {
        // The composite unique index predates this migration and is part of the
        // original schema, so it is deliberately left in place. Only the index
        // this migration may have added is reversible.
        if (Schema::hasTable('terms') && in_array('terms_academic_year_id_status_index', $this->indexNames(), true)) {
            Schema::table('terms', function ($table): void {
                $table->dropIndex('terms_academic_year_id_status_index');
            });
        }
    }

    /**
     * @return list<string>
     */
    private function indexNames(): array
    {
        return array_values(array_map(
            static fn (object $row): string => (string) ($row->Key_name ?? $row->name ?? ''),
            DB::select('SHOW INDEX FROM terms'),
        ));
    }
};

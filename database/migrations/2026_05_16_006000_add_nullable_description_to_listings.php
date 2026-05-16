<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make `listings.description` nullable.
 *
 * The wizard saves a `state = 'draft'` row after step 1 (before the user
 * has even reached the description field), so the original NOT NULL
 * constraint forced a placeholder string. That placeholder leaked into
 * the listings table and confused the search index, the moderation queue,
 * and at least one bewildered new contributor. Relaxing the column to
 * nullable lets drafts be honest about their incomplete state; the
 * wizard refuses to transition a draft to `pending_review` while
 * description is empty (see `Wizard::next()` step-2 validation), so
 * published rows still have a value.
 *
 * Because `search_vector` is a STORED generated column referencing
 * `description`, we drop and recreate it to wrap the now-nullable
 * column in `coalesce(...)` (it already was — so this is a no-op
 * semantically, but Postgres requires re-declaring the generation
 * expression when the source column's nullability changes for any
 * column referenced inside `GENERATED ALWAYS AS`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE listings DROP COLUMN search_vector');
        DB::statement('ALTER TABLE listings ALTER COLUMN description DROP NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE listings
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                to_tsvector('dutch', coalesce(title,'') || ' ' || coalesce(description,''))
            ) STORED
        SQL);
        DB::statement('CREATE INDEX listings_search_gin ON listings USING GIN (search_vector)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE listings DROP COLUMN search_vector');
        DB::statement("UPDATE listings SET description = '' WHERE description IS NULL");
        DB::statement('ALTER TABLE listings ALTER COLUMN description SET NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE listings
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                to_tsvector('dutch', coalesce(title,'') || ' ' || coalesce(description,''))
            ) STORED
        SQL);
        DB::statement('CREATE INDEX listings_search_gin ON listings USING GIN (search_vector)');
    }
};

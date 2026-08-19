<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eén herinnering per concept, nooit twee.
 *
 * Zonder deze kolom zou de dagelijkse herinnering elke dag opnieuw dezelfde
 * mail sturen aan iemand die zijn concept bewust laat staan. Dat is precies
 * het soort platform dat we niet willen zijn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $t) {
            $t->timestamp('draft_reminded_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $t) {
            $t->dropColumn('draft_reminded_at');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aantal identieke exemplaren achter één advertentie.
 *
 * Een verkoper met vier dezelfde ThinkCentres liep de wizard vier keer door en
 * zette "(2x)" in de omschrijving van zijn servers: hij had het veld zelf al
 * bedacht, het bestond alleen niet. Gevolg: zes van de twaalf kaarten op de
 * eerste pagina van het aanbod waren van één verkoper.
 *
 * `quantity` telt wat er nog ís. Verkoopt hij er één, dan gaat er eentje af;
 * bij de laatste gaat de advertentie op `sold`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $t) {
            $t->unsignedSmallInteger('quantity')->default(1)->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $t) {
            $t->dropColumn('quantity');
        });
    }
};

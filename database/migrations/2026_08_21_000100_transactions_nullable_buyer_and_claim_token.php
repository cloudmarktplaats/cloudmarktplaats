<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Een gemelde verkoop zonder bekende koper is vanaf nu een geldige rij.
     *
     * De contact-relay is bewust anoniem: een koper heeft geen account nodig
     * en onthult alleen een e-mailadres. De verkoper kan zijn gebruikersnaam
     * dus niet kennen. In plaats daarvan legt elke melding een transactie vast
     * met een claim-token; de koper vult zichzelf in door die link te openen.
     *
     * De CHECK `transactions_buyer_ne_seller` blijft ongemoeid: in Postgres
     * slaagt een CHECK die op NULL uitkomt.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE transactions ALTER COLUMN buyer_user_id DROP NOT NULL');

        Schema::table('transactions', function (Blueprint $t) {
            $t->string('claim_token', 32)->nullable()->unique();
            $t->timestamp('claim_expires_at')->nullable();
        });
    }

    /**
     * Terugdraaien faalt zolang er verkopen zonder koper staan. Dat is
     * opzettelijk: die rijen weggooien om een migratie te laten slagen is
     * geen beslissing die een migratie mag nemen.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropColumn(['claim_token', 'claim_expires_at']);
        });

        DB::statement('ALTER TABLE transactions ALTER COLUMN buyer_user_id SET NOT NULL');
    }
};

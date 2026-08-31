<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_subscriptions', function (Blueprint $t) {
            // Het parkeervak van de dubbele opt-in. Wie niet kan bewijzen dat
            // een bevestigd adres van hem is, verandert de rij niet maar zet
            // zijn gevraagde wijziging hier neer; pas de klik op de
            // bevestigingslink uit die mailbox past hem toe. Zo kan een vreemde
            // op het publieke formulier een bewezen inschrijving niet
            // overschrijven of terugzetten naar onbevestigd.
            $t->jsonb('pending_changes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mail_subscriptions', function (Blueprint $t) {
            $t->dropColumn('pending_changes');
        });
    }
};

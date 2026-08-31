<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Het logboek van verstuurde nieuwsbrieven: 1 regel per editie.
     *
     * De 30-dagenrem in `mail:update` leunt hierop, en met opzet niet meer op
     * `mail_subscriptions.updates_sent_at`. Die kolom hangt aan een persoon en
     * verdwijnt met hem mee: een inschrijving hangt met ON DELETE CASCADE aan
     * het account, dus 1 lid dat zijn account wist, wiste de datum van de laatste
     * nieuwsbrief mee en opende daarmee de rem. Wie er op de lijst staat is een
     * andere vraag dan wat er verstuurd is, en die twee horen dus niet in
     * dezelfde tabel te wonen.
     *
     * `updates_sent_at` blijft bestaan en blijft nuttig: die zegt wanneer déze
     * persoon voor het laatst een nieuwsbrief kreeg. Alleen de rem leest hem niet
     * meer.
     *
     * Geen `user_id` en geen adressen hier. Dit is een logboek van wat er uitging,
     * geen tweede kopie van de lijst.
     */
    public function up(): void
    {
        Schema::create('mail_editions', function (Blueprint $t) {
            $t->id();
            $t->timestamp('sent_at');
            // Het aantal en het bestand staan er om achteraf te kunnen nalezen
            // wat er verstuurd is. Zonder die twee is een regel hier een datum
            // zonder inhoud.
            $t->unsignedInteger('recipient_count');
            $t->string('source_file');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_editions');
    }
};

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
            // Het moment waarop een toestemming werd ingetrokken. Zonder dit
            // veld blijven `consent_text`, `consent_given_at` en
            // `consent_source` na een afmelding naar een toestemming wijzen die
            // niet meer bestaat, en is er nergens vastgelegd dat en wanneer
            // iemand nee zei. Bewijs van een ingetrokken toestemming is onder
            // art. 7 lid 1 AVG net zo goed bewijs; leeg betekent "op dit moment
            // niets ingetrokken".
            $t->timestamp('unsubscribed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mail_subscriptions', function (Blueprint $t) {
            $t->dropColumn('unsubscribed_at');
        });
    }
};

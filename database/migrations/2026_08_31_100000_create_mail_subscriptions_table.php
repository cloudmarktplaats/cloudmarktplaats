<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
            // Leeg is "geen account". Dit veld ís de segmentatie, en de cascade
            // zorgt dat accountverwijdering de inschrijving meeneemt.
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->boolean('wants_offers')->default(false);
            $t->boolean('wants_updates')->default(false);
            $t->jsonb('categories')->default('[]');
            $t->string('confirm_token')->nullable()->unique();
            $t->timestamp('confirmed_at')->nullable();
            $t->string('unsubscribe_token')->unique();
            // De letterlijke zin waarop iemand ja zei. Geen versienummer dat
            // naar een tekst wijst: verandert de formulering, dan is oud bewijs
            // anders onleesbaar.
            $t->text('consent_text');
            $t->timestamp('consent_given_at');
            $t->string('consent_source');
            $t->timestamp('offers_sent_at')->nullable();
            $t->timestamp('updates_sent_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_subscriptions');
    }
};

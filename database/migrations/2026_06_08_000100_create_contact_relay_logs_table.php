<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minimal abuse/rate-metrics ledger for the seller-contact relay.
     *
     * DELIBERATELY tiny: it records that *a* message was relayed for a
     * listing and when — nothing else. No buyer email, no message body,
     * no IP. The relay is one-way email; we never archive its contents
     * (see the privacy statement). This table exists only so moderation
     * can spot abuse patterns and so rate-limit metrics have a source.
     */
    public function up(): void
    {
        Schema::create('contact_relay_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->timestamp('created_at')->nullable();
            $t->index(['listing_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_relay_logs');
    }
};

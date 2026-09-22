<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('feed_source_id')->constrained()->cascadeOnDelete();
            $t->string('guid');
            $t->string('title');
            $t->string('url');
            // Samenvatting is HTML-gestript en ingekort; geen images.
            $t->text('summary')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamp('fetched_at');
            $t->timestamps();
            // Dedup: dezelfde guid binnen 1 bron is hetzelfde artikel.
            $t->unique(['feed_source_id', 'guid']);
            // De reader sorteert altijd op nieuwste eerst.
            $t->index(['feed_source_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
    }
};

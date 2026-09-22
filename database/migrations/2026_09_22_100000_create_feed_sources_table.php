<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_sources', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('feed_url');
            $t->string('homepage_url')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort')->default(0);
            // Zichtbare storing: wanneer de laatste fetch lukte, en de laatste
            // fout als er 1 was. De reader toont dit subtiel per bron.
            $t->timestamp('last_fetched_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamp('last_error_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_sources');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feed_sources', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('feed_source_id')->constrained()->cascadeOnDelete();
            $t->primary(['user_id', 'feed_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feed_sources');
    }
};

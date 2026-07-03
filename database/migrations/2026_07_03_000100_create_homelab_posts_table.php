<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homelab_posts', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();
            // Interne verantwoordingslijn — wordt nooit publiek gerenderd.
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->string('photo_disk')->default('local');
            $t->string('photo_path');
            $t->enum('status', ['published', 'removed'])->default('published');
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homelab_posts');
    }
};

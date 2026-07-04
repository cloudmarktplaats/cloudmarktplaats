<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homelab_post_upvotes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('homelab_post_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'homelab_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homelab_post_upvotes');
    }
};

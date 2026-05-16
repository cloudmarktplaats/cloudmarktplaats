<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('action');
            $t->string('target_type');
            $t->unsignedBigInteger('target_id');
            $t->jsonb('meta')->nullable();
            $t->string('ip_hash', 64);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['target_type', 'target_id']);
            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_identities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('provider', ['password', 'oauth_github', 'oauth_gitlab', 'siwe']);
            $t->string('provider_uid');
            $t->jsonb('provider_data')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
            $t->unique(['provider', 'provider_uid']);
            $t->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identities');
    }
};

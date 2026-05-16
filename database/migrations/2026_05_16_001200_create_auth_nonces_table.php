<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_nonces', function (Blueprint $t) {
            $t->id();
            $t->string('nonce', 64)->unique();
            $t->string('address', 64);
            $t->timestamp('expires_at')->index();
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_nonces');
    }
};

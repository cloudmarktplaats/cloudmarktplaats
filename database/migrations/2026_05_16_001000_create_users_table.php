<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();
            $t->string('email')->unique();
            $t->string('password_hash')->nullable();
            $t->string('username', 30)->unique();
            $t->string('display_name');
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->string('last_login_ip', 45)->nullable();
            $t->enum('role', ['user', 'moderator', 'admin'])->default('user');
            $t->boolean('is_banned')->default(false);
            $t->text('banned_reason')->nullable();
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invite_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->foreignId('inviter_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('invitee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
            $t->index('inviter_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_codes');
    }
};

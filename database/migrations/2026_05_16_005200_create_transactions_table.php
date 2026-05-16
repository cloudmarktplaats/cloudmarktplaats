<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('seller_user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedInteger('amount_cents');
            $t->string('currency', 3)->default('EUR');
            $t->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $t->timestamp('completed_at')->nullable();
            $t->boolean('off_platform')->default(true);
            $t->string('external_tx_ref')->nullable();
            $t->timestamps();
            $t->index(['seller_user_id', 'status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

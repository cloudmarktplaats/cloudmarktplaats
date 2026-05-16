<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->morphs('reportable');
            $t->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->enum('reason', ['illegal', 'stolen', 'spam', 'wrong_category', 'other']);
            $t->text('details')->nullable();
            $t->enum('status', ['open', 'resolved', 'dismissed'])->default('open');
            $t->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('resolution_note')->nullable();
            $t->timestamps();
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};

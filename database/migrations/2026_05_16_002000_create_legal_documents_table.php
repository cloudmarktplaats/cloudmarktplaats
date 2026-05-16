<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['tos', 'privacy']);
            $t->string('version', 20);
            $t->string('locale', 5);
            $t->text('markdown_content');
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->index(['type', 'locale', 'published_at']);
            $t->unique(['type', 'locale', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};

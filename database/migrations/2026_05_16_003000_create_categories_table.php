<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('icon')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        DB::statement('ALTER TABLE categories ADD COLUMN path ltree NOT NULL');
        DB::statement('CREATE INDEX categories_path_gist ON categories USING GIST (path)');
        DB::statement('CREATE UNIQUE INDEX categories_path_unique ON categories (path)');
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

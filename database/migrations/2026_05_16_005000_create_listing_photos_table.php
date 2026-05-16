<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_photos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->string('disk', 16)->default('local');
            $t->string('path');
            $t->unsignedSmallInteger('width');
            $t->unsignedSmallInteger('height');
            $t->string('mime', 64);
            $t->unsignedInteger('byte_size');
            $t->unsignedTinyInteger('position');
            $t->timestamps();
            $t->unique(['listing_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_photos');
    }
};

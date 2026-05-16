<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $t) {
            $t->id();
            $t->ulid('ulid')->unique();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('title');
            $t->string('slug');
            $t->text('description');
            $t->enum('condition', ['new', 'used', 'defective', 'for_parts']);
            $t->unsignedInteger('price_cents');
            $t->string('currency', 3)->default('EUR');
            $t->boolean('is_trade_allowed')->default(false);
            $t->char('region_postcode', 4)->nullable();
            $t->jsonb('shipping_options')->default(DB::raw("'{\"pickup\":true,\"post\":false}'::jsonb"));
            $t->enum('state', ['draft', 'pending_review', 'published', 'sold', 'archived', 'rejected'])->default('draft');
            $t->timestamp('published_at')->nullable();
            $t->timestamp('sold_at')->nullable();
            $t->text('moderation_notes')->nullable();
            $t->unsignedInteger('view_count')->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['user_id', 'slug']);
            $t->index('state');
            $t->index('category_id');
            $t->index(['state', 'published_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE listings
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                to_tsvector('dutch', coalesce(title,'') || ' ' || coalesce(description,''))
            ) STORED
        SQL);
        DB::statement('CREATE INDEX listings_search_gin ON listings USING GIN (search_vector)');
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_buyer_ne_seller CHECK (buyer_user_id <> seller_user_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_buyer_ne_seller');
    }
};

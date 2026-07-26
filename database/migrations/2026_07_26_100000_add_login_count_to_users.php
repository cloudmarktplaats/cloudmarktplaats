<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->unsignedInteger('login_count')->default(0)->after('last_login_ip');
        });

        // Backfill: anyone who has ever logged in gets at least 1. We never
        // tracked the real historical count, so this is a floor, not the truth.
        DB::table('users')
            ->whereNotNull('last_login_at')
            ->update(['login_count' => 1]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('login_count');
        });
    }
};

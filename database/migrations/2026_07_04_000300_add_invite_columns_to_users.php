<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('invited_by')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $t->unsignedInteger('invite_credits')->default(0)->after('invited_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('invited_by');
            $t->dropColumn('invite_credits');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zakelijke verkopers, zodat een consument weet van wie hij koopt.
 *
 * Zodra een bedrijf aan een particulier verkoopt gelden conformiteit,
 * herroeping bij verzending en een informatieplicht — regels die geen van
 * beide partijen kan wegcontracteren. Het platform kende dat onderscheid niet,
 * terwijl er al bedrijfshardware verkocht wordt.
 *
 * Default `private`: zakelijk zijn is een bewuste handeling, en dat is ook de
 * veilige kant om op te vallen. Zie
 * docs/superpowers/specs/2026-08-19-zakelijke-verkoper-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('seller_type', 16)->default('private')->after('role');
            $t->string('business_name')->nullable()->after('seller_type');
            $t->string('business_registration', 32)->nullable()->after('business_name');
            $t->string('business_vat', 32)->nullable()->after('business_registration');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_seller_type_check CHECK (seller_type IN ('private', 'business'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_seller_type_check');
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['seller_type', 'business_name', 'business_registration', 'business_vat']);
        });
    }
};

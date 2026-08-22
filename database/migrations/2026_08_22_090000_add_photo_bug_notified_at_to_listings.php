<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onthouden wie er al gemaild is over een concept dat vastliep op de
 * foto-upload.
 *
 * `listings:notify-photo-bug` hield dat nergens bij. Gevolg: op 22-08 was niet
 * meer vast te stellen of de mail van 14 juli ooit verstuurd is, en dus of
 * opnieuw draaien de eerste lichting een tweede keer zou lastigvallen. Een
 * commando dat mensen mailt en dat niet noteert, kun je maar één keer met een
 * gerust hart draaien.
 *
 * Bewust apart van `draft_reminded_at`: dat is de gewone conceptherinnering
 * (`listings:remind-drafts`). Iemand mag beide krijgen — het zijn twee
 * verschillende boodschappen — maar elk hooguit één keer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $t): void {
            $t->timestamp('photo_bug_notified_at')->nullable()->after('draft_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $t): void {
            $t->dropColumn('photo_bug_notified_at');
        });
    }
};

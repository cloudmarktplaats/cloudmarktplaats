<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Elk homelab een eigen pagina: titel, feedback-vraag, en foto's in een eigen
 * tabel (spiegel van listing_photos) in plaats van twee kolommen op de post.
 *
 * De mime-kolom is de kern: zonder de bron-mime is original.{ext} — en dus een
 * deelbare og:image — niet te bouwen. Dat is precies wat HomelabPost::photoUrl()
 * nu met een exception afdwingt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homelab_posts', function (Blueprint $t): void {
            $t->string('title', 120)->nullable()->after('ulid');
            $t->string('feedback_prompt', 280)->nullable()->after('body');
            $t->boolean('comments_open')->default(true)->after('feedback_prompt');
        });

        Schema::create('homelab_photos', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('homelab_post_id')->constrained()->cascadeOnDelete();
            $t->string('disk', 16)->default('local');
            $t->string('path');
            $t->unsignedSmallInteger('width');
            $t->unsignedSmallInteger('height');
            $t->string('mime', 64);
            $t->unsignedInteger('byte_size');
            $t->unsignedTinyInteger('position');
            $t->timestamps();
            $t->unique(['homelab_post_id', 'position']);
        });

        // Bestaande posts hun foto meegeven op position 0. De bron-mime is niet
        // te achterhalen (photo_path wijst naar de card-webp), dus zetten we de
        // mime van het bestand waar path werkelijk naar wijst: image/webp. Niet
        // gokken naar jpg/png — dat bouwt exact de 404 die deze feature oplost.
        // Gevolg: og:image valt voor deze twee terug op og-default.png, precies
        // zoals de webp-regel voorschrijft.
        $posts = DB::table('homelab_posts')
            ->whereNotNull('photo_path')
            ->where('photo_path', '!=', 'pending')
            ->get(['id', 'photo_disk', 'photo_path']);

        foreach ($posts as $post) {
            [$width, $height] = $this->dimensionsFor((string) $post->photo_disk, (string) $post->photo_path);

            DB::table('homelab_photos')->insert([
                'homelab_post_id' => $post->id,
                'disk' => (string) $post->photo_disk,
                'path' => (string) $post->photo_path,
                'width' => $width,
                'height' => $height,
                'mime' => 'image/webp',
                'byte_size' => $this->byteSizeFor((string) $post->photo_disk, (string) $post->photo_path),
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @return array{0:int,1:int} */
    private function dimensionsFor(string $disk, string $path): array
    {
        try {
            $bytes = Storage::disk($disk)->get($path);
            $info = $bytes !== null ? getimagesizefromstring($bytes) : false;
            if ($info !== false) {
                return [(int) $info[0], (int) $info[1]];
            }
        } catch (Throwable) {
            // Bestand weg of onleesbaar — val terug op de card-afmeting (600x600).
        }

        return [600, 600];
    }

    private function byteSizeFor(string $disk, string $path): int
    {
        try {
            return (int) Storage::disk($disk)->size($path);
        } catch (Throwable) {
            return 0;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('homelab_photos');
        Schema::table('homelab_posts', function (Blueprint $t): void {
            $t->dropColumn(['title', 'feedback_prompt', 'comments_open']);
        });
    }
};

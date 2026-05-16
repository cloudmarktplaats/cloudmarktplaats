<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * StorageInterface implementation backed by Laravel's `public` disk.
 * Files written here are exposed at `<APP_URL>/storage/...` after
 * `php artisan storage:link` has been run (see deployment docs).
 *
 * The disk is resolved per-call rather than cached at construction so
 * that tests calling `Storage::fake('public')` after the container has
 * already built the StorageManager still hit the fake disk.
 */
class LocalStorage implements StorageInterface
{
    private ?Filesystem $disk = null;

    public function __construct() {}

    /**
     * Override the underlying disk (used by tests that want a custom
     * fake). Production callers should leave this alone and let
     * {@see disk()} resolve `Storage::disk('public')` lazily.
     */
    public function useDisk(Filesystem $disk): void
    {
        $this->disk = $disk;
    }

    private function disk(): Filesystem
    {
        return $this->disk ?? Storage::disk('public');
    }

    public function put(string $path, string $contents, array $options = []): string
    {
        $this->disk()->put($path, $contents, $options);

        return $path;
    }

    public function get(string $path): string
    {
        $bytes = $this->disk()->get($path);
        if ($bytes === null) {
            throw new RuntimeException("File not found: {$path}");
        }

        return $bytes;
    }

    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    public function delete(string $path): bool
    {
        return $this->disk()->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }
}

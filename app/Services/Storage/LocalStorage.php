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
 */
class LocalStorage implements StorageInterface
{
    private Filesystem $disk;

    public function __construct(?Filesystem $disk = null)
    {
        $this->disk = $disk ?? Storage::disk('public');
    }

    public function put(string $path, string $contents, array $options = []): string
    {
        $this->disk->put($path, $contents, $options);

        return $path;
    }

    public function get(string $path): string
    {
        $bytes = $this->disk->get($path);
        if ($bytes === null) {
            throw new RuntimeException("File not found: {$path}");
        }

        return $bytes;
    }

    public function url(string $path): string
    {
        return $this->disk->url($path);
    }

    public function delete(string $path): bool
    {
        return $this->disk->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }
}

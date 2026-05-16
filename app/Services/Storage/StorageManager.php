<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

/**
 * Driver registry for the marketplace's storage abstraction.
 *
 * Maps short driver names ('local', future 's3', 'ipfs') to concrete
 * {@see StorageInterface} implementations. Resolution is lazy and
 * cached per request, so callers can fetch the same driver many times
 * (e.g. once per ListingPhoto::urlFor() in a listing detail view)
 * without instantiating extra objects.
 */
class StorageManager
{
    /** @var array<string, class-string<StorageInterface>> */
    public const DRIVERS = [
        'local' => LocalStorage::class,
    ];

    /** @var array<string, StorageInterface> */
    private array $resolved = [];

    public function __construct(private Application $app) {}

    public function driver(?string $name = null): StorageInterface
    {
        $name ??= (string) config('cloudmarktplaats.storage.driver', 'local');

        if (! array_key_exists($name, self::DRIVERS)) {
            throw new InvalidArgumentException("Unknown storage driver: {$name}");
        }

        return $this->resolved[$name] ??= $this->app->make(self::DRIVERS[$name]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Storage;

/**
 * Lightweight storage abstraction used by the listing photo pipeline and
 * any future binary uploads (avatars, attachments).
 *
 * Implementations are resolved by {@see StorageManager} based on the
 * `cloudmarktplaats.storage.driver` config key, so swapping the
 * default disk (e.g. local → S3/MinIO) is a one-line config change.
 *
 * The marketplace wraps Laravel's filesystem rather than depending on it
 * directly so that:
 *   - tests can fake a driver without touching the global `Storage` facade
 *   - future drivers (IPFS pinning, signed S3 URLs with CDN) can implement
 *     the same five operations
 *   - URL generation is driver-aware (local serves `/storage/...`, S3
 *     returns CDN URLs, IPFS returns `ipfs://...`)
 */
interface StorageInterface
{
    /**
     * Persist a blob at `$path`. Returns the canonical stored path.
     *
     * @param  array<string, mixed>  $options
     */
    public function put(string $path, string $contents, array $options = []): string;

    /**
     * Read the blob at `$path`. Throws on missing file.
     */
    public function get(string $path): string;

    /**
     * Generate a public URL for `$path`.
     */
    public function url(string $path): string;

    /**
     * Delete the blob at `$path`. Returns true if the file existed.
     */
    public function delete(string $path): bool;

    /**
     * True iff a blob exists at `$path`.
     */
    public function exists(string $path): bool;
}

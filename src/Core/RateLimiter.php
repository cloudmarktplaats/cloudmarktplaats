<?php

namespace App\Core;

class RateLimiter
{
    private string $fallbackDir;
    private bool $useApcu;

    public function __construct(?string $fallbackDir = null)
    {
        $this->useApcu = function_exists('apcu_enabled') && apcu_enabled();
        $this->fallbackDir = $fallbackDir ?? sys_get_temp_dir() . '/cmp_ratelimit';
        if (!$this->useApcu && !is_dir($this->fallbackDir)) {
            @mkdir($this->fallbackDir, 0700, true);
        }
    }

    public function attempt(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        $now = time();
        $entry = $this->read($key);

        if ($entry === null || $entry['window_start'] + $windowSeconds < $now) {
            $entry = ['count' => 0, 'window_start' => $now];
        }

        if ($entry['count'] >= $maxAttempts) {
            return false;
        }

        $entry['count']++;
        $this->write($key, $entry, $windowSeconds);
        return true;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        $entry = $this->read($key);
        if ($entry === null) {
            return $maxAttempts;
        }
        return max(0, $maxAttempts - $entry['count']);
    }

    public function reset(string $key): void
    {
        if ($this->useApcu) {
            apcu_delete($this->storageKey($key));
            return;
        }
        $path = $this->filePath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function read(string $key): ?array
    {
        if ($this->useApcu) {
            $val = apcu_fetch($this->storageKey($key), $success);
            return $success ? $val : null;
        }
        $path = $this->filePath($key);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = @unserialize($raw);
        return is_array($data) ? $data : null;
    }

    private function write(string $key, array $entry, int $ttl): void
    {
        if ($this->useApcu) {
            apcu_store($this->storageKey($key), $entry, $ttl);
            return;
        }
        @file_put_contents($this->filePath($key), serialize($entry), LOCK_EX);
    }

    private function storageKey(string $key): string
    {
        return 'cmp_ratelimit:' . $key;
    }

    private function filePath(string $key): string
    {
        return $this->fallbackDir . '/' . hash('sha256', $key);
    }
}

<?php

namespace App\Core;

use Dotenv\Dotenv;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->load();
        $dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

        self::$config = $_ENV;
        self::$loaded = true;

        date_default_timezone_set(self::get('APP_TIMEZONE', 'Europe/Amsterdam'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$config[$key] ?? $default;

        if ($value === null) {
            return $default;
        }

        // Cast string booleans
        if (is_string($value)) {
            $lower = strtolower($value);
            if ($lower === 'true') return true;
            if ($lower === 'false') return false;
        }

        return $value;
    }

    public static function isDebug(): bool
    {
        return (bool) self::get('APP_DEBUG', false);
    }

    public static function database(): array
    {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'name' => self::get('DB_NAME'),
            'user' => self::get('DB_USER'),
            'pass' => self::get('DB_PASS', ''),
        ];
    }

    public static function reset(): void
    {
        self::$config = [];
        self::$loaded = false;
    }
}

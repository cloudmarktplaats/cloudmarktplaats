<?php

namespace App\Core;

use Dotenv\Dotenv;

class Config {
    public function load(): void {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        
        // Valideer verplichte environment variabelen
        $dotenv->required([
            'DB_HOST',
            'DB_NAME',
            'DB_USER',
            'DB_PASS',
            'APP_NAME',
            'APP_URL',
            'JWT_SECRET'
        ]);
    }
} 
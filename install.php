<?php
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Lees het SQL bestand
    $sql = file_get_contents('database.sql');

    // Voer de SQL uit
    $pdo->exec($sql);

    echo "Database tabellen succesvol aangemaakt!";
} catch (PDOException $e) {
    die("Database installatie mislukt: " . $e->getMessage());
} 
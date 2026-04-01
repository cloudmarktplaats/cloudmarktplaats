<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;

Config::load(dirname(__DIR__));
$db = Database::getInstance();

// Create migrations table if not exists
$db->query("CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Get already executed migrations
$executed = $db->fetchAll("SELECT filename FROM migrations ORDER BY filename");
$executedFiles = array_column($executed, 'filename');

// Find migration files
$files = glob(__DIR__ . '/*.sql');
sort($files);

$count = 0;
foreach ($files as $file) {
    $filename = basename($file);
    if (in_array($filename, $executedFiles)) {
        echo "SKIP: {$filename} (already executed)\n";
        continue;
    }

    echo "RUN:  {$filename} ... ";
    $sql = file_get_contents($file);

    try {
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $db->query($statement);
            }
        }

        $db->insert('migrations', ['filename' => $filename]);
        echo "OK\n";
        $count++;
    } catch (\Exception $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nDone. {$count} migration(s) executed.\n";

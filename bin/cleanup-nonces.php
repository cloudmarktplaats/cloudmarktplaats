<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Models\AuthNonce;

Config::load(dirname(__DIR__));
Database::getInstance();

$model = new AuthNonce();
$deleted = $model->deleteExpired(86400);

echo date('[Y-m-d H:i:s] ') . "Deleted {$deleted} expired nonce rows.\n";

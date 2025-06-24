<?php
// Database configuratie
define('DB_HOST', 'localhost');
define('DB_NAME', 'cloudmarkplaats1');
define('DB_USER', 'root');
define('DB_PASS', '');

// App configuratie
define('APP_NAME', 'Cloudmarkplaats.nl');
define('APP_URL', 'http://localhost');

// Kleuren
define('COLOR_PRIMARY', '#0B132B');    // Oxford Blue
define('COLOR_SECONDARY', '#1C2541');  // Space Cadet
define('COLOR_ACCENT', '#5BC0BE');     // Verdigris
define('COLOR_TEXT', '#FFFFFF');       // White
define('COLOR_HIGHLIGHT', '#3A506B');  // Yinmn Blue

// App instellingen
define('MAX_PRODUCT_IMAGES', 5);
define('MAX_PRODUCT_TAGS', 5);
define('REQUIRE_APPROVAL', true);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1); 
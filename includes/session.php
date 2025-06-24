<?php
// Sessie configuratie
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Zet op 1 als je HTTPS gebruikt

// Start sessie
if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 
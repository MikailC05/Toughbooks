<?php
/**
 * Toughbooks Configurator - Configuratie bestand
 */

// Database configuratie
define('DB_HOST', 'localhost');
define('DB_NAME', 'toughbooks');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Applicatie instellingen
define('APP_NAME', 'Toughbooks Configurator');
define('APP_VERSION', '1.0.0');

// Debug mode (zet op false in productie!)
define('DEBUG_MODE', true);

// Error reporting
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Europe/Amsterdam');

// Session configuratie
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
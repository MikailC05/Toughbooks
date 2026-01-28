<?php
require_once __DIR__ . '/src/Database.php';

$dbFile = __DIR__ . '/data/toughbooks.db';
if (file_exists($dbFile)) {
    unlink($dbFile);
}

// Recreate by instantiating Database
Database::getInstance();

echo "Database reset and initialized. <a href=\"admin_login.php\">Back to admin</a>";

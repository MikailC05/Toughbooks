<?php
// Simple DB connectivity + counts check (no PowerShell quoting issues)
require_once __DIR__ . '/../src/Database.php';

try {
    $pdo = Database::getInstance()->getPdo();

    $laptops = (int)$pdo->query('SELECT COUNT(*) FROM laptops')->fetchColumn();
    $questions = (int)$pdo->query('SELECT COUNT(*) FROM questions')->fetchColumn();
    $scores = (int)$pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn();

    echo "OK\n";
    echo "laptops={$laptops}\n";
    echo "questions={$questions}\n";
    echo "scores={$scores}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

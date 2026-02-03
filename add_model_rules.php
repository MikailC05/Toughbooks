<?php
// Script om extra modelnummer regels toe te voegen
require_once __DIR__ . '/src/Database.php';

try {
    $pdo = Database::getInstance()->getPdo();

    // Lees het SQL bestand
    $sqlFile = __DIR__ . '/migrations/add_more_model_rules.sql';

    if (!file_exists($sqlFile)) {
        die("❌ SQL bestand niet gevonden: $sqlFile\n");
    }

    $sql = file_get_contents($sqlFile);

    // Verwijder commentaren en splits statements
    $statements = array_filter(
        array_map(
            'trim',
            explode(';', preg_replace('/--.*$/m', '', $sql))
        ),
        function($stmt) {
            return !empty($stmt);
        }
    );

    foreach ($statements as $statement) {
        if (trim($statement)) {
            try {
                $pdo->exec($statement);
                echo "✓ Statement uitgevoerd\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo "⚠ Overgeslagen (bestaat al)\n";
                } else {
                    throw $e;
                }
            }
        }
    }

    echo "\n✅ Extra modelnummer regels toegevoegd!\n";
    echo "Je kunt nu naar het admin panel gaan (tab Modelnummers) om ze te bekijken en bewerken.\n";

} catch (Exception $e) {
    echo "❌ Fout bij uitvoeren:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

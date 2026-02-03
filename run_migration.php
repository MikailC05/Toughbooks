<?php
// Script om de modelnummer systeem migratie uit te voeren
require_once __DIR__ . '/src/Database.php';

try {
    $pdo = Database::getInstance()->getPdo();

    // Lees het SQL bestand
    $sqlFile = __DIR__ . '/migrations/create_model_number_system.sql';

    if (!file_exists($sqlFile)) {
        die("❌ Migratie bestand niet gevonden: $sqlFile\n");
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
                // Als tabel al bestaat, negeer de fout
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
                echo "⚠ Overgeslagen (bestaat al)\n";
            }
        }
    }

    echo "\n✅ Migratie succesvol uitgevoerd!\n";
    echo "De volgende tabellen zijn aangemaakt/bijgewerkt:\n";
    echo "  - model_number_rules\n";
    echo "  - configuration_options\n";
    echo "\nJe kunt nu naar het admin panel gaan om modelnummers te beheren.\n";

} catch (Exception $e) {
    echo "❌ Fout bij uitvoeren van migratie:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

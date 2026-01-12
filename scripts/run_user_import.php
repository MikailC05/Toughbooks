<?php
// Run import: reads a CSV and inserts laptops + laptop_specs + scores.
require_once __DIR__ . '/../src/Database.php';
$pdo = Database::getInstance()->getPdo();
$path = __DIR__ . '/../data/user_import_cf33.csv';
if ($argc > 1) $path = $argv[1];
if (!file_exists($path)) { echo "File not found: $path\n"; exit(1); }

$rows = [];
if (($handle = fopen($path, 'r')) !== false) {
    while (($line = fgetcsv($handle)) !== false) $rows[] = $line;
    fclose($handle);
}
if (empty($rows) || count($rows) < 2) { echo "No data rows found in $path\n"; exit(1); }
$headers = array_map('trim', $rows[0]);
$added = 0; $skipped = 0; $errors = [];
for ($i = 1; $i < count($rows); $i++) {
    $row = $rows[$i];
    if (count($row) < count($headers)) $row = array_pad($row, count($headers), '');
    $data = @array_combine($headers, $row);
    if (!$data) { $errors[] = "Row $i: malformed"; $skipped++; continue; }
    $modelCode = trim($data['model_code'] ?? '');
    if ($modelCode === '') { $errors[] = "Row $i: missing model_code"; $skipped++; continue; }

    // skip if exists
    $exists = $pdo->prepare('SELECT id FROM laptops WHERE model_code = ? LIMIT 1');
    $exists->execute([$modelCode]);
    if ($exists->fetch()) { $skipped++; echo "Skipping existing model_code: $modelCode\n"; continue; }

    $name = trim($data['name'] ?? $modelCode);
    $price = isset($data['price']) ? floatval($data['price']) : 0.0;
    $description = trim($data['description'] ?? '');
    $specKeys = ['gps','touchscreen','cellular','keyboard','form_factor','display','ram','storage'];
    $specs = [];
    foreach ($specKeys as $k) {
        if (isset($data[$k]) && $data[$k] !== '') $specs[$k] = $data[$k];
        elseif (isset($data['spec_' . $k]) && $data['spec_' . $k] !== '') $specs[$k] = $data['spec_' . $k];
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO laptops (name, model_code, description, price_eur, is_active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$name, $modelCode, $description, $price]);
        $laptopId = $pdo->lastInsertId();
        if (!empty($specs)) {
            $specStmt = $pdo->prepare('INSERT INTO laptop_specs (laptop_id, spec_key, spec_value, spec_type) VALUES (?, ?, ?, ?)');
            foreach ($specs as $key => $val) $specStmt->execute([$laptopId, $key, $val, 'text']);
        }

        // scoring: use existing questions and options
        $questionsStmt = $pdo->query('SELECT q.id as question_id, q.text as question_text, q.description, o.id as option_id, o.value as option_value FROM questions q JOIN options o ON q.id = o.question_id ORDER BY q.id, o.display_order');
        $questionsData = $questionsStmt->fetchAll();
        $scoreStmt = $pdo->prepare('INSERT INTO scores (laptop_id, option_id, points, reason) VALUES (?, ?, ?, ?)');
        foreach ($questionsData as $qData) {
            $questionText = strtolower($qData['question_text'] . ' ' . ($qData['description'] ?? ''));
            $optionValue = $qData['option_value']; $points = 0; $reason = 'Automatisch berekend';
            if (strpos($questionText, 'gps') !== false) {
                $hasGPS = isset($specs['gps']) && (strtolower($specs['gps']) === 'yes' || stripos($specs['gps'],'yes')!==false || strtolower($specs['gps'])==='1');
                if ($optionValue === 'yes' && $hasGPS) { $points = 30; $reason = 'Heeft GPS'; }
                elseif ($optionValue === 'no' && !$hasGPS) { $points = 30; $reason = 'Geen GPS'; }
            } elseif (strpos($questionText, 'touch') !== false) {
                $hasTouch = isset($specs['touchscreen']) && (strtolower($specs['touchscreen']) === 'yes' || stripos($specs['touchscreen'],'yes')!==false);
                if ($optionValue === 'yes' && $hasTouch) { $points = 30; $reason = 'Heeft touchscreen'; }
                elseif ($optionValue === 'no' && !$hasTouch) { $points = 30; $reason = 'Geen touchscreen'; }
            } elseif (strpos($questionText, '4g') !== false || strpos($questionText, '5g') !== false || strpos($questionText, 'mobiel') !== false) {
                $hasCellular = isset($specs['cellular']) && strtolower($specs['cellular']) !== 'none' && $specs['cellular'] !== '';
                if ($optionValue === 'yes' && $hasCellular) { $points = 30; $reason = 'Heeft mobiel internet'; }
                elseif ($optionValue === 'no' && !$hasCellular) { $points = 30; $reason = 'Geen mobiel internet'; }
            }
            $scoreStmt->execute([$laptopId, $qData['option_id'], $points, $reason]);
        }

        $pdo->commit();
        $added++;
        echo "Inserted: $modelCode ($name)\n";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors[] = "Row $i ($modelCode): " . $e->getMessage();
        $skipped++;
    }
}

echo "\nImport complete. Added: $added, Skipped: $skipped\n";
if (!empty($errors)) { echo "Errors:\n"; foreach ($errors as $err) echo " - $err\n"; }

exit(0);

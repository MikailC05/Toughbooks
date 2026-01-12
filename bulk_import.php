<?php
session_start();
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';

Auth::requireLogin();

$pdo = Database::getInstance()->getPdo();
$message = '';
$error = '';

// Handle uploaded CSV/Excel (CSV or XLSX)
function parse_xlsx($filePath) {
    $rows = [];
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return $rows;

    // read shared strings
    $shared = [];
    if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
        $s = $zip->getFromIndex($idx);
        $xml = simplexml_load_string($s);
        $ns = $xml->getNamespaces(true);
        foreach ($xml->si as $si) {
            $t = '';
            if (isset($si->t)) $t = (string)$si->t;
            else {
                foreach ($si->children() as $c) { $t .= (string)$c; }
            }
            $shared[] = $t;
        }
    }

    // read first worksheet
    if (($idx = $zip->locateName('xl/worksheets/sheet1.xml')) !== false) {
        $s = $zip->getFromIndex($idx);
        $xml = simplexml_load_string($s);
        $rowsXml = [];
        foreach ($xml->sheetData->row as $r) {
            $row = [];
            foreach ($r->c as $c) {
                $ref = (string)$c['r'];
                // get column letters
                $col = preg_replace('/[0-9]+/', '', $ref);
                $v = '';
                if (isset($c->v)) {
                    $v = (string)$c->v;
                    $t = (string)$c['t'];
                    if ($t === 's') {
                        $index = intval($v);
                        $v = $shared[$index] ?? $v;
                    }
                }
                $row[$col] = $v;
            }
            $rowsXml[] = $row;
        }

        // normalize rows by columns order
        $columns = [];
        if (!empty($rowsXml)) {
            // derive all columns used
            foreach ($rowsXml as $r) { foreach ($r as $col => $_) if (!in_array($col, $columns)) $columns[] = $col; }
            foreach ($rowsXml as $r) {
                $line = [];
                foreach ($columns as $col) { $line[] = $r[$col] ?? ''; }
                $rows[] = $line;
            }
        }
    }

    $zip->close();
    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $files = $_FILES['csv_file'];
    $totalAdded = 0; $totalSkipped = 0; $errorsArr = [];

    // normalize to array of uploads
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;
    for ($fi = 0; $fi < $fileCount; $fi++) {
        $name = is_array($files['name']) ? $files['name'][$fi] : $files['name'];
        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$fi] : $files['tmp_name'];
        $errorCode = is_array($files['error']) ? $files['error'][$fi] : $files['error'];

        if ($errorCode !== UPLOAD_ERR_OK) { $errorsArr[] = "Upload fout voor $name (code $errorCode)"; continue; }

        $originalName = strtolower($name);
        $rows = [];
        if (substr($originalName, -5) === '.xlsx') {
            $rows = parse_xlsx($tmpName);
        } else {
            // try CSV
            if (($handle = fopen($tmpName, 'r')) !== false) {
                while (($line = fgetcsv($handle)) !== false) {
                    $rows[] = $line;
                }
                fclose($handle);
            }
        }

        if (empty($rows) || count($rows) < 1) { $errorsArr[] = "Bestand $name bevat geen data of is ongeldig."; continue; }

        $headers = array_map('trim', $rows[0]);
        $added = 0; $skipped = 0;
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (count($row) < count($headers)) $row = array_pad($row, count($headers), '');
            $data = @array_combine($headers, $row);
            if (!$data) { $skipped++; continue; }

            $modelCode = trim($data['model_code'] ?? '');
            if ($modelCode === '') { $skipped++; continue; }
            $exists = $pdo->prepare('SELECT id FROM laptops WHERE model_code = ? LIMIT 1');
            $exists->execute([$modelCode]);
            if ($exists->fetch()) { $skipped++; continue; }

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

                // scoring
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

                $pdo->commit(); $added++;
            } catch (Exception $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $skipped++; }
        }

        $totalAdded += $added; $totalSkipped += $skipped;
    }

    $message = "Import compleet. Toegevoegd: $totalAdded. Overgeslagen: $totalSkipped.";
    if (!empty($errorsArr)) $error = implode("; ", $errorsArr);
}

?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Importeer Toughbooks (CSV)</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="container">
    <h1>CSV Importeren</h1>
    <p class="muted">Upload een CSV-bestand. Kopteksten: <strong>model_code</strong> (verplicht), optioneel: <strong>name, price, description</strong> en specs zoals <strong>gps, touchscreen, cellular, keyboard, form_factor, display, ram, storage</strong>. Excel kan als CSV worden opgeslagen.</p>

    <?php if ($message): ?><div class="success">✓ <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error">✗ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>CSV / Excel bestand (.csv of .xlsx)</label>
                <input type="file" name="csv_file" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" multiple required>
            </div>
        <button type="submit" class="btn btn-primary">📤 Importeren</button>
        <a href="admin.php" class="btn btn-secondary">Terug naar Admin</a>
    </form>

    <hr>
    <h3>Voorbeeld CSV</h3>
    <pre>model_code,name,price,description,gps,touchscreen,cellular,keyboard,form_factor,display,ram,storage
CF-33YAAACB4,Toughbook CF-33 16GB 512GB SSD 4G,2500,Tablet only 16GB 512GB SSD 4G GPS,Yes,No,4G,AZERTY,Tablet Only,FHD,16GB,512GB SSD</pre>
</main>
</body>
</html>

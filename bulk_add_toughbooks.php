<?php
// Bulk toevoegen van alle Toughbooks met automatische score berekening
require_once __DIR__ . '/src/Database.php';

$pdo = Database::getInstance()->getPdo();

// Alle Toughbook configuraties
$toughbooks = [
    // CF-33 modellen
    ['model_code' => 'CF-33YAAACB4', 'name' => 'Toughbook CF-33 16GB 512GB SSD 4G', 'description' => 'Tablet only 16GB 512GB SSD 4G GPS', 'price' => 2500, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => '4G', 'gps' => 'Yes', 'form_factor' => 'Tablet Only']],
    ['model_code' => 'CF-339AAACB4', 'name' => 'Toughbook CF-33 16GB 512GB SSD 4G', 'description' => '2-in-1 16GB 512GB SSD 4G GPS QWERTY', 'price' => 2700, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => '4G', 'gps' => 'Yes', 'keyboard' => 'QWERTY', 'form_factor' => '2-in-1']],
    ['model_code' => 'CF-339AAACBL', 'name' => 'Toughbook CF-33 16GB 512GB SSD 4G', 'description' => '2-in-1 16GB 512GB SSD 4G GPS AZERTY', 'price' => 2700, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => '4G', 'gps' => 'Yes', 'keyboard' => 'AZERTY', 'form_factor' => '2-in-1']],
    ['model_code' => 'CF-33YAAAXB4', 'name' => 'Toughbook CF-33 16GB 512GB SSD', 'description' => 'Tablet only 16GB 512GB SSD WLAN', 'price' => 2200, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => 'None', 'form_factor' => 'Tablet Only']],
    ['model_code' => 'CF-339Z00CB4', 'name' => 'Toughbook CF-33 16GB 512GB SSD', 'description' => '2-in-1 16GB 512GB SSD WLAN QWERTY', 'price' => 2400, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => 'None', 'keyboard' => 'QWERTY', 'form_factor' => '2-in-1']],
    ['model_code' => 'CF-339Z01AB4', 'name' => 'Toughbook CF-33 16GB 512GB SSD', 'description' => '2-in-1 16GB 512GB SSD WLAN AZERTY', 'price' => 2400, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => 'None', 'keyboard' => 'AZERTY', 'form_factor' => '2-in-1']],
    ['model_code' => 'CF-33YZ01CB4', 'name' => 'Toughbook CF-33 16GB 512GB SSD 5G', 'description' => 'Tablet only 16GB 512GB SSD 5G GPS', 'price' => 2800, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => '5G', 'gps' => 'Yes', 'form_factor' => 'Tablet Only']],
    
    // FZ-40 modellen
    ['model_code' => 'FZ-40FZ00NB4', 'name' => 'Toughbook FZ-40 16GB 512GB SSD', 'description' => '16GB 512GB SSD WLAN Touch FHD AZERTY', 'price' => 3200, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => 'None', 'touchscreen' => 'Yes', 'display' => 'FHD', 'keyboard' => 'AZERTY']],
    ['model_code' => 'FZ-40FZ00MB4', 'name' => 'Toughbook FZ-40 16GB 512GB SSD', 'description' => '16GB 512GB SSD WLAN Touch FHD QWERTY', 'price' => 3200, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => 'None', 'touchscreen' => 'Yes', 'display' => 'FHD', 'keyboard' => 'QWERTY']],
    ['model_code' => 'FZ-40FZ003B4', 'name' => 'Toughbook FZ-40 16GB 512GB SSD 4G', 'description' => '16GB 512GB SSD 4G GPS Touch FHD AZERTY', 'price' => 3500, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => '4G', 'gps' => 'Yes', 'touchscreen' => 'Yes', 'display' => 'FHD', 'keyboard' => 'AZERTY']],
    ['model_code' => 'FZ-40FZ00CB4', 'name' => 'Toughbook FZ-40 16GB 512GB SSD 4G', 'description' => '16GB 512GB SSD 4G GPS Touch FHD QWERTY', 'price' => 3500, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => '4G', 'gps' => 'Yes', 'touchscreen' => 'Yes', 'display' => 'FHD', 'keyboard' => 'QWERTY']],
    
    // FZ-55 modellen
    ['model_code' => 'FZ-55GZ00WB4', 'name' => 'Toughbook FZ-55 16GB 512GB SSD', 'description' => '16GB 512GB SSD WLAN non-Touch HD AZERTY', 'price' => 2800, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => 'None', 'touchscreen' => 'No', 'display' => 'HD', 'keyboard' => 'AZERTY']],
    ['model_code' => 'FZ-55G6601B4', 'name' => 'Toughbook FZ-55 16GB 512GB SSD', 'description' => '16GB 512GB SSD WLAN non-Touch HD QWERTY', 'price' => 2800, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => 'None', 'touchscreen' => 'No', 'display' => 'HD', 'keyboard' => 'QWERTY']],
    ['model_code' => 'FZ-55GZ00XB4', 'name' => 'Toughbook FZ-55 16GB 512GB SSD 4G', 'description' => '16GB 512GB SSD 4G GPS non-Touch HD AZERTY', 'price' => 3100, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => '4G', 'gps' => 'Yes', 'touchscreen' => 'No', 'display' => 'HD', 'keyboard' => 'AZERTY']],
    ['model_code' => 'FZ-55GZ010B4', 'name' => 'Toughbook FZ-55 16GB 512GB SSD 4G', 'description' => '16GB 512GB SSD 4G GPS non-Touch HD QWERTY', 'price' => 3100, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => '4G', 'gps' => 'Yes', 'touchscreen' => 'No', 'display' => 'HD', 'keyboard' => 'QWERTY']],
    ['model_code' => 'FZ-55JZ00YB4', 'name' => 'Toughbook FZ-55 16GB 512GB SSD', 'description' => '16GB 512GB SSD WLAN Touch FHD AZERTY', 'price' => 3000, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => 'None', 'touchscreen' => 'Yes', 'display' => 'FHD', 'keyboard' => 'AZERTY']],
    ['model_code' => 'FZ-55J2601B4', 'name' => 'Toughbook FZ-55 16GB 512GB SSD', 'description' => '16GB 512GB SSD WLAN Touch FHD QWERTY', 'price' => 3000, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => 'None', 'touchscreen' => 'Yes', 'display' => 'FHD', 'keyboard' => 'QWERTY']],
    ['model_code' => 'FZ-55JZ00ZB4', 'name' => 'Toughbook FZ-55 16GB 512GB SSD 4G', 'description' => '16GB 512GB SSD 4G GPS Touch FHD AZERTY', 'price' => 3300, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => '4G', 'gps' => 'Yes', 'touchscreen' => 'Yes', 'display' => 'FHD', 'keyboard' => 'AZERTY']],
    ['model_code' => 'FZ-55JZ011B4', 'name' => 'Toughbook FZ-55 16GB 512GB SSD 4G', 'description' => '16GB 512GB SSD 4G GPS Touch FHD QWERTY', 'price' => 3300, 'specs' => ['ram' => '16GB', 'storage' => '512GB SSD', 'cellular' => '4G', 'gps' => 'Yes', 'touchscreen' => 'Yes', 'display' => 'FHD', 'keyboard' => 'QWERTY']],
];

echo "<h1>Bulk Toughbooks Toevoegen</h1>";
echo "<p>Bezig met toevoegen van " . count($toughbooks) . " Toughbooks...</p>";

$added = 0;
$skipped = 0;

foreach ($toughbooks as $tb) {
    try {
        // Check of modelcode al bestaat
        $check = $pdo->prepare('SELECT id FROM laptops WHERE model_code = ?');
        $check->execute([$tb['model_code']]);
        
        if ($check->fetch()) {
            echo "<div style='color: orange;'>⚠️  {$tb['model_code']} bestaat al - overgeslagen</div>";
            $skipped++;
            continue;
        }
        
        $pdo->beginTransaction();
        
        // Voeg laptop toe
        $stmt = $pdo->prepare('INSERT INTO laptops (name, model_code, description, price_eur, is_active) VALUES (?, ?, ?, ?, 1)');
        $stmt->execute([$tb['name'], $tb['model_code'], $tb['description'], $tb['price']]);
        $laptopId = $pdo->lastInsertId();
        
        // Voeg specs toe
        $specStmt = $pdo->prepare('INSERT INTO laptop_specs (laptop_id, spec_key, spec_value, spec_type) VALUES (?, ?, ?, ?)');
        foreach ($tb['specs'] as $key => $value) {
            $specStmt->execute([$laptopId, $key, $value, 'text']);
        }
        
        // Voeg automatische scores toe voor alle vragen
        $questionsStmt = $pdo->query('
            SELECT q.id as question_id, q.text as question_text, q.description,
                   o.id as option_id, o.value as option_value
            FROM questions q
            JOIN options o ON q.id = o.question_id
            ORDER BY q.id, o.display_order
        ');
        $questionsData = $questionsStmt->fetchAll();
        
        $scoreStmt = $pdo->prepare('INSERT INTO scores (laptop_id, option_id, points, reason) VALUES (?, ?, ?, ?)');
        
        foreach ($questionsData as $qData) {
            $questionText = strtolower($qData['question_text'] . ' ' . ($qData['description'] ?? ''));
            $optionValue = $qData['option_value'];
            $points = 0;
            $reason = 'Automatisch berekend';
            
            // GPS
            if (strpos($questionText, 'gps') !== false || strpos($questionText, 'navigatie') !== false) {
                $hasGPS = isset($tb['specs']['gps']) && $tb['specs']['gps'] === 'Yes';
                if ($optionValue === 'yes' && $hasGPS) {
                    $points = 30;
                    $reason = 'Heeft GPS';
                } elseif ($optionValue === 'no' && !$hasGPS) {
                    $points = 30;
                    $reason = 'Geen GPS (past bij gebruiker)';
                }
            }
            // Touchscreen
            elseif (strpos($questionText, 'touchscreen') !== false || strpos($questionText, 'aanraak') !== false) {
                $hasTouch = isset($tb['specs']['touchscreen']) && $tb['specs']['touchscreen'] === 'Yes';
                if ($optionValue === 'yes' && $hasTouch) {
                    $points = 30;
                    $reason = 'Heeft touchscreen';
                } elseif ($optionValue === 'no' && !$hasTouch) {
                    $points = 30;
                    $reason = 'Geen touchscreen';
                }
            }
            // 4G/5G
            elseif (strpos($questionText, '4g') !== false || strpos($questionText, '5g') !== false || strpos($questionText, 'mobiel') !== false) {
                $hasCellular = isset($tb['specs']['cellular']) && $tb['specs']['cellular'] !== 'None';
                if ($optionValue === 'yes' && $hasCellular) {
                    $points = 30;
                    $reason = 'Heeft mobiel internet';
                } elseif ($optionValue === 'no' && !$hasCellular) {
                    $points = 30;
                    $reason = 'Geen mobiel internet';
                }
            }
            // AZERTY
            elseif (strpos($questionText, 'azerty') !== false || strpos($questionText, 'belgisch') !== false) {
                $hasAzerty = isset($tb['specs']['keyboard']) && strpos($tb['specs']['keyboard'], 'AZERTY') !== false;
                if ($optionValue === 'yes' && $hasAzerty) {
                    $points = 30;
                    $reason = 'AZERTY toetsenbord';
                } elseif ($optionValue === 'no' && !$hasAzerty) {
                    $points = 30;
                    $reason = 'QWERTY toetsenbord';
                }
            }
            // Tablet
            elseif (strpos($questionText, 'tablet') !== false) {
                $isTablet = isset($tb['specs']['form_factor']) && strpos($tb['specs']['form_factor'], 'Tablet') !== false;
                if ($optionValue === 'yes' && $isTablet) {
                    $points = 30;
                    $reason = 'Is tablet';
                } elseif ($optionValue === 'no' && !$isTablet) {
                    $points = 30;
                    $reason = 'Geen tablet';
                }
            }
            // 2-in-1
            elseif (strpos($questionText, '2-in-1') !== false || strpos($questionText, 'afneembaar') !== false) {
                $is2in1 = isset($tb['specs']['form_factor']) && strpos($tb['specs']['form_factor'], '2-in-1') !== false;
                if ($optionValue === 'yes' && $is2in1) {
                    $points = 30;
                    $reason = 'Is 2-in-1';
                } elseif ($optionValue === 'no' && !$is2in1) {
                    $points = 30;
                    $reason = 'Geen 2-in-1';
                }
            }
            // FHD
            elseif (strpos($questionText, 'full hd') !== false || strpos($questionText, 'fhd') !== false) {
                $hasFHD = isset($tb['specs']['display']) && $tb['specs']['display'] === 'FHD';
                if ($optionValue === 'yes' && $hasFHD) {
                    $points = 30;
                    $reason = 'Full HD scherm';
                } elseif ($optionValue === 'no' && !$hasFHD) {
                    $points = 30;
                    $reason = 'HD scherm';
                }
            }
            
            $scoreStmt->execute([$laptopId, $qData['option_id'], $points, $reason]);
        }
        
        $pdo->commit();
        echo "<div style='color: green;'>✓ {$tb['model_code']} - {$tb['name']} toegevoegd</div>";
        $added++;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "<div style='color: red;'>✗ Fout bij {$tb['model_code']}: " . $e->getMessage() . "</div>";
    }
}

echo "<hr>";
echo "<h2>Samenvatting:</h2>";
echo "<p>✅ Toegevoegd: $added</p>";
echo "<p>⚠️  Overgeslagen: $skipped</p>";
echo "<p><a href='admin.php'>→ Terug naar Admin Panel</a></p>";

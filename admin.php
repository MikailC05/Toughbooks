<?php
session_start();
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';

// Forceer altijd eerst de login-pagina als iemand admin.php opent.
// Het admin panel zelf wordt benaderd via admin.php?panel=1.
if (!isset($_GET['panel']) || (string)$_GET['panel'] !== '1') {
    $redirect = urlencode('admin.php?panel=1');
    header('Location: admin_login.php?redirect=' . $redirect);
    exit;
}

Auth::requireLogin();

$pdo = Database::getInstance()->getPdo();
$message = '';
$error = '';
$currentUser = Auth::getCurrentUser();

// ============================================================================
// LAPTOP CONFIGURATIE (per-laptop extra opties)
// ============================================================================
function ensureLaptopConfigSchema(PDO $pdo): void
{
    // Idempotent schema (MySQL/MariaDB)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS laptop_config_fields (\n"
        . "  id INT(11) NOT NULL AUTO_INCREMENT,\n"
        . "  laptop_id INT(11) NOT NULL,\n"
        . "  field_key VARCHAR(64) NOT NULL,\n"
        . "  field_label VARCHAR(255) NOT NULL,\n"
        . "  field_type VARCHAR(20) NOT NULL,\n"
        . "  default_value VARCHAR(255) DEFAULT NULL,\n"
        . "  is_active TINYINT(1) DEFAULT 1,\n"
        . "  sort_order INT(11) DEFAULT 0,\n"
        . "  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  PRIMARY KEY (id),\n"
        . "  UNIQUE KEY unique_laptop_field (laptop_id, field_key),\n"
        . "  KEY idx_laptop (laptop_id),\n"
        . "  CONSTRAINT fk_laptop_config_fields_laptop FOREIGN KEY (laptop_id) REFERENCES laptops(id) ON DELETE CASCADE\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS laptop_config_field_options (\n"
        . "  id INT(11) NOT NULL AUTO_INCREMENT,\n"
        . "  field_id INT(11) NOT NULL,\n"
        . "  option_label VARCHAR(255) NOT NULL,\n"
        . "  option_value VARCHAR(255) NOT NULL,\n"
        . "  image_path VARCHAR(500) DEFAULT NULL,\n"
        . "  sort_order INT(11) DEFAULT 0,\n"
        . "  is_default TINYINT(1) DEFAULT 0,\n"
        . "  PRIMARY KEY (id),\n"
        . "  KEY idx_field (field_id),\n"
        . "  CONSTRAINT fk_laptop_config_options_field FOREIGN KEY (field_id) REFERENCES laptop_config_fields(id) ON DELETE CASCADE\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Add image_path column if it doesn't exist (for existing installations)
    try {
        $pdo->exec("ALTER TABLE laptop_config_field_options ADD COLUMN image_path VARCHAR(500) DEFAULT NULL AFTER option_value");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
}

function getDefaultLaptopConfigFieldDefinitions(): array
{
    return [
        'model_variant' => ['label' => 'Model variant', 'type' => 'select', 'default_options' => ['Tablet', 'Detachable']],
        'processor' => ['label' => 'Processor', 'type' => 'select', 'default_options' => ['Intel Core i5', 'Intel Core i7']],
        'storage' => ['label' => 'Opslag', 'type' => 'select', 'default_options' => ['256GB', '512GB']],
        'ram' => ['label' => 'RAM', 'type' => 'select', 'default_options' => ['8GB', '16GB']],
        'lte' => ['label' => '4G LTE (optioneel)', 'type' => 'boolean', 'default_value' => '0'],
        'gps' => ['label' => 'GPS (optioneel)', 'type' => 'boolean', 'default_value' => '0'],
        'battery' => ['label' => 'Batterij', 'type' => 'select', 'default_options' => ['Standaard', 'High Capacity']],
        'config_area_1' => [
            'label' => 'Configuration area 1',
            'type' => 'select',
            'default_options' => [
                'Empty (default)',
                'Serial interface',
                '2nd USB 2.0 connection',
                '2D barcode reader',
            ],
        ],
        'config_area_2' => [
            'label' => 'Configuration area 2',
            'type' => 'select',
            'default_options' => [
                'Empty (default)',
                'SmartCard reader',
                'Contactless SmartCard reader / NFC',
                'Fingerprint reader',
            ],
        ],
    ];
}

function seedLaptopConfigIfMissing(PDO $pdo, int $laptopId): void
{
    $check = $pdo->prepare('SELECT COUNT(*) FROM laptop_config_fields WHERE laptop_id = ?');
    $check->execute([$laptopId]);
    $count = (int)$check->fetchColumn();
    if ($count > 0) {
        return;
    }

    $defs = getDefaultLaptopConfigFieldDefinitions();
    $pdo->beginTransaction();
    try {
        $insertField = $pdo->prepare(
            'INSERT INTO laptop_config_fields (laptop_id, field_key, field_label, field_type, default_value, is_active, sort_order) '
            . 'VALUES (?, ?, ?, ?, ?, 1, ?)'
        );
        $insertOpt = $pdo->prepare(
            'INSERT INTO laptop_config_field_options (field_id, option_label, option_value, sort_order, is_default) '
            . 'VALUES (?, ?, ?, ?, ?)'
        );

        $sort = 1;
        foreach ($defs as $key => $def) {
            $defaultValue = $def['default_value'] ?? null;
            if (($def['type'] ?? '') === 'select') {
                $opts = $def['default_options'] ?? [];
                $defaultValue = !empty($opts) ? (string)$opts[0] : null;
            }
            $insertField->execute([$laptopId, $key, $def['label'], $def['type'], $defaultValue, $sort]);
            $fieldId = (int)$pdo->lastInsertId();

            if (($def['type'] ?? '') === 'select') {
                $opts = $def['default_options'] ?? [];
                $o = 1;
                foreach ($opts as $opt) {
                    $isDefault = ($o === 1) ? 1 : 0;
                    $insertOpt->execute([$fieldId, $opt, $opt, $o, $isDefault]);
                    $o++;
                }
            }

            $sort++;
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

// ============================================================================
// ADMIN GEBRUIKER TOEVOEGEN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $username = trim($_POST['admin_username']);
    $password = $_POST['admin_password'];
    $password_confirm = $_POST['admin_password_confirm'];
    
    if ($password !== $password_confirm) {
        $error = 'Wachtwoorden komen niet overeen!';
    } elseif (strlen($password) < 6) {
        $error = 'Wachtwoord moet minimaal 6 tekens zijn!';
    } elseif ($username === '') {
        $error = 'Gebruikersnaam mag niet leeg zijn!';
    } else {
        try {
            // Check of gebruiker al bestaat
            $check = $pdo->prepare('SELECT id FROM admin_users WHERE username = ?');
            $check->execute([$username]);
            
            if ($check->fetch()) {
                $error = 'Deze gebruikersnaam bestaat al!';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
                $stmt->execute([$username, $hash]);
                $message = "Admin gebruiker '$username' succesvol aangemaakt!";
            }
        } catch (Exception $e) {
            $error = 'Fout: ' . $e->getMessage();
        }
    }
}

// ============================================================================
// ADMIN GEBRUIKER VERWIJDEREN
// ============================================================================
if (isset($_GET['delete_admin'])) {
    $adminId = (int)$_GET['delete_admin'];
    
    // Voorkom dat gebruiker zichzelf verwijdert
    if ($adminId === $currentUser['id']) {
        $error = 'Je kunt jezelf niet verwijderen!';
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM admin_users WHERE id = ?');
            $stmt->execute([$adminId]);
            $message = 'Admin gebruiker succesvol verwijderd!';
        } catch (Exception $e) {
            $error = 'Fout: ' . $e->getMessage();
        }
    }
}

// ============================================================================
// ADMIN WACHTWOORD WIJZIGEN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $adminId = (int)$_POST['admin_id'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if ($newPassword !== $confirmPassword) {
        $error = 'Wachtwoorden komen niet overeen!';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Wachtwoord moet minimaal 6 tekens zijn!';
    } else {
        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $adminId]);
            $message = 'Wachtwoord succesvol gewijzigd!';
        } catch (Exception $e) {
            $error = 'Fout: ' . $e->getMessage();
        }
    }
}

// ============================================================================
// VRAAG VERWIJDEREN
// ============================================================================
if (isset($_GET['delete_question'])) {
    $qid = (int)$_GET['delete_question'];
    try {
        $pdo->beginTransaction();
        
        $optIds = $pdo->prepare('SELECT id FROM options WHERE question_id = ?');
        $optIds->execute([$qid]);
        $options = $optIds->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($options)) {
            $placeholders = implode(',', array_fill(0, count($options), '?'));
            $delScores = $pdo->prepare("DELETE FROM scores WHERE option_id IN ($placeholders)");
            $delScores->execute($options);
        }
        
        $delOpts = $pdo->prepare('DELETE FROM options WHERE question_id = ?');
        $delOpts->execute([$qid]);
        
        $delQ = $pdo->prepare('DELETE FROM questions WHERE id = ?');
        $delQ->execute([$qid]);
        
        $pdo->commit();
        $message = 'Vraag succesvol verwijderd!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Fout bij verwijderen: ' . $e->getMessage();
    }
}

// ============================================================================
// VRAAG TOEVOEGEN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $text = trim($_POST['question_text']);
    $description = trim($_POST['question_description'] ?? '');
    $weight = floatval($_POST['question_weight'] ?? 1.0);
    
    if ($text !== '') {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->query('SELECT MAX(display_order) as max_order FROM questions');
            $maxOrder = $stmt->fetch()['max_order'] ?? 0;
            
            $stmt = $pdo->prepare('INSERT INTO questions (text, type, description, weight, display_order) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$text, 'boolean', $description, $weight, $maxOrder + 1]);
            $qid = $pdo->lastInsertId();
            
            $optStmt = $pdo->prepare('INSERT INTO options (question_id, label, value, display_order) VALUES (?, ?, ?, ?)');
            $optStmt->execute([$qid, 'Ja', 'yes', 1]);
            $yesId = $pdo->lastInsertId();
            $optStmt->execute([$qid, 'Nee', 'no', 2]);
            $noId = $pdo->lastInsertId();
            
            $laptops = $pdo->query('SELECT id FROM laptops WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
            $scoreStmt = $pdo->prepare('INSERT INTO scores (laptop_id, option_id, points, reason) VALUES (?, ?, ?, ?)');
            foreach ($laptops as $lid) {
                $scoreStmt->execute([$lid, $yesId, 0, 'Standaard score']);
                $scoreStmt->execute([$lid, $noId, 0, 'Niet van toepassing']);
            }
            
            $pdo->commit();
            $message = 'Vraag succesvol toegevoegd! Je kunt nu de scores aanpassen door op "Bewerken" te klikken.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Fout: ' . $e->getMessage();
        }
    }
}

// ============================================================================
// VRAAG BEWERKEN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_question'])) {
    $qid = (int)$_POST['question_id'];
    $text = trim($_POST['question_text']);
    $description = trim($_POST['question_description'] ?? '');
    $weight = floatval($_POST['question_weight'] ?? 1.0);
    
    try {
        $stmt = $pdo->prepare('UPDATE questions SET text = ?, description = ?, weight = ? WHERE id = ?');
        $stmt->execute([$text, $description, $weight, $qid]);
        $message = 'Vraag succesvol bijgewerkt!';
    } catch (Exception $e) {
        $error = 'Fout: ' . $e->getMessage();
    }
}

// ============================================================================
// SCORES BEWERKEN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_scores'])) {
    $qid = (int)$_POST['question_id'];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['scores'] as $scoreId => $data) {
            // checkbox-based: match = 1/0
            $points = !empty($data['match']) ? 1 : 0;
            $reason = trim($data['reason'] ?? '');
            
            $stmt = $pdo->prepare('UPDATE scores SET points = ?, reason = ? WHERE id = ?');
            $stmt->execute([$points, $reason, $scoreId]);
        }
        
        $pdo->commit();
        $message = 'Scores succesvol bijgewerkt! Deze punten worden gebruikt om de beste laptop te bepalen.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Fout: ' . $e->getMessage();
    }
}

// =========================================================================
// KOPPELINGEN REPAREREN (laptop x optie => score)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair_mappings'])) {
    try {
        $pdo->beginTransaction();

        // Maak ontbrekende scores aan voor alle bestaande combinaties.
        // Dit voorkomt “lege” koppelingen als laptops/vraag-opties later zijn toegevoegd.
        $sql = <<<SQL
    INSERT INTO scores (laptop_id, option_id, points, reason)
    SELECT l.id, o.id, 0, 'Auto toegevoegd'
    FROM laptops l
    CROSS JOIN options o
    LEFT JOIN scores s ON s.laptop_id = l.id AND s.option_id = o.id
    WHERE s.id IS NULL
    SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $added = $stmt->rowCount();

        $pdo->commit();
        $message = "Koppelingen gerepareerd: {$added} ontbrekende score(s) toegevoegd.";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Fout bij repareren: ' . $e->getMessage();
    }
}

// =========================================================================
// SCORES PER LAPTOP BEWERKEN (laptop-centrisch)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_laptop_scores'])) {
    $laptopId = (int)($_POST['laptop_id'] ?? 0);

    if ($laptopId <= 0) {
        $error = 'Ongeldige laptop.';
    } else {
        try {
            $pdo->beginTransaction();

            // Gebruik UPSERT zodat we niet afhankelijk zijn van rowCount()
            // (MySQL/MariaDB geeft 0 rows affected terug als waarden hetzelfde blijven).
            $upsertStmt = $pdo->prepare(
                'INSERT INTO scores (laptop_id, option_id, points, reason) VALUES (?, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE points = VALUES(points), reason = VALUES(reason)'
            );

            // Nieuw (laptop-UI): per vraag 1 keuze (ja/nee/geen)
            if (!empty($_POST['choices']) && is_array($_POST['choices'])) {
                $yesOptions = $_POST['yes_option'] ?? [];
                $noOptions = $_POST['no_option'] ?? [];
                $reasons = $_POST['reason'] ?? [];

                foreach ($_POST['choices'] as $questionId => $selectedOptionId) {
                    if (!ctype_digit((string)$questionId)) {
                        continue;
                    }

                    $qid = (int)$questionId;
                    $yesId = isset($yesOptions[$qid]) && ctype_digit((string)$yesOptions[$qid]) ? (int)$yesOptions[$qid] : 0;
                    $noId = isset($noOptions[$qid]) && ctype_digit((string)$noOptions[$qid]) ? (int)$noOptions[$qid] : 0;
                    $selected = ctype_digit((string)$selectedOptionId) ? (int)$selectedOptionId : 0;
                    $reason = is_array($reasons) && isset($reasons[$qid]) ? trim((string)$reasons[$qid]) : '';

                    if ($yesId > 0) {
                        $upsertStmt->execute([$laptopId, $yesId, ($selected === $yesId) ? 1 : 0, ($selected === $yesId) ? $reason : '']);
                    }
                    if ($noId > 0) {
                        $upsertStmt->execute([$laptopId, $noId, ($selected === $noId) ? 1 : 0, ($selected === $noId) ? $reason : '']);
                    }
                }
            } else {
                // Fallback: oude checkbox-based payload (laat dit staan voor compatibiliteit)
                foreach (($_POST['scores'] ?? []) as $optionId => $data) {
                    if (!ctype_digit((string)$optionId)) {
                        continue;
                    }
                    $oid = (int)$optionId;
                    $points = !empty($data['match']) ? 1 : 0;
                    $reason = trim((string)($data['reason'] ?? ''));

                    $upsertStmt->execute([$laptopId, $oid, $points, $reason]);
                }
            }

            $pdo->commit();
            $message = 'Scores voor deze laptop zijn opgeslagen!';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Fout bij opslaan: ' . $e->getMessage();
        }
    }
}

// ============================================================================
// LAPTOPS BEHEREN (TOEVOEGEN / BIJWERKEN / VERWIJDEREN)
// ============================================================================
// Laptop toevoegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_laptop'])) {
    $name = trim($_POST['laptop_name'] ?? '');
    $price = isset($_POST['laptop_price_eur']) ? floatval($_POST['laptop_price_eur']) : 0.0;

    if ($name === '') {
        $error = 'Laptop naam mag niet leeg zijn!';
    } elseif ($price < 0) {
        $error = 'Prijs kan niet negatief zijn!';
    } else {
        try {
            // Validatie: naam moet uniek zijn (DB heeft UNIQUE op name)
            $exists = $pdo->prepare('SELECT id FROM laptops WHERE name = ? LIMIT 1');
            $exists->execute([$name]);
            if ($exists->fetch()) {
                $error = 'Deze laptopnaam bestaat al.';
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('INSERT INTO laptops (name, price_eur, is_active) VALUES (?, ?, 1)');
                $stmt->execute([$name, $price]);
                $laptopId = $pdo->lastInsertId();

                // Zorg voor standaard scores voor alle bestaande opties, zodat bewerken werkt
                $optStmt = $pdo->query('SELECT id FROM options');
                $optionIds = $optStmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($optionIds)) {
                    $scoreStmt = $pdo->prepare('INSERT INTO scores (laptop_id, option_id, points, reason) VALUES (?, ?, ?, ?)');
                    foreach ($optionIds as $oid) {
                        $scoreStmt->execute([$laptopId, $oid, 0, 'Standaard score']);
                    }
                }

                $pdo->commit();
                $message = 'Laptop succesvol toegevoegd!';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $error = 'Deze laptopnaam bestaat al.';
            } else {
                $error = 'Fout bij toevoegen van laptop: ' . $e->getMessage();
            }
        }
    }
}

// Laptop bijwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_laptop'])) {
    $laptopId = (int)($_POST['laptop_id'] ?? 0);
    $name = trim($_POST['laptop_name'] ?? '');
    $price = isset($_POST['laptop_price_eur']) ? floatval($_POST['laptop_price_eur']) : 0.0;
    $isActive = isset($_POST['laptop_is_active']) ? 1 : 0;

    if ($name === '') {
        $error = 'Laptop naam mag niet leeg zijn!';
    } elseif ($price < 0) {
        $error = 'Prijs kan niet negatief zijn!';
    } else {
        try {
            // Validatie: naam mag niet al bestaan bij een andere laptop
            $exists = $pdo->prepare('SELECT id FROM laptops WHERE name = ? AND id <> ? LIMIT 1');
            $exists->execute([$name, $laptopId]);
            if ($exists->fetch()) {
                $error = 'Deze laptopnaam is al in gebruik.';
            } else {
                $stmt = $pdo->prepare('UPDATE laptops SET name = ?, price_eur = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$name, $price, $isActive, $laptopId]);
                $message = 'Laptop succesvol bijgewerkt!';
            }
        } catch (Exception $e) {
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $error = 'Deze laptopnaam is al in gebruik.';
            } else {
                $error = 'Fout bij bijwerken van laptop: ' . $e->getMessage();
            }
        }
    }
}

// ============================================================================
// OPTIE AFBEELDING UPLOADEN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_option_image'])) {
    $optionId = (int)($_POST['option_id'] ?? 0);
    $laptopId = (int)($_POST['laptop_id'] ?? 0);

    if ($optionId <= 0) {
        $error = 'Ongeldige optie.';
    } elseif (!isset($_FILES['option_image']) || $_FILES['option_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Geen afbeelding geüpload of upload fout.';
    } else {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['option_image']['type'];

        if (!in_array($fileType, $allowedTypes)) {
            $error = 'Alleen JPG, PNG, GIF en WEBP afbeeldingen zijn toegestaan.';
        } else {
            $uploadDir = __DIR__ . '/uploads/options/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = pathinfo($_FILES['option_image']['name'], PATHINFO_EXTENSION);
            $filename = 'option_' . $optionId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['option_image']['tmp_name'], $targetPath)) {
                try {
                    // Delete old image if exists
                    $oldImg = $pdo->prepare('SELECT image_path FROM laptop_config_field_options WHERE id = ?');
                    $oldImg->execute([$optionId]);
                    $oldPath = $oldImg->fetchColumn();
                    if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
                        unlink(__DIR__ . '/' . $oldPath);
                    }

                    $stmt = $pdo->prepare('UPDATE laptop_config_field_options SET image_path = ? WHERE id = ?');
                    $stmt->execute(['uploads/options/' . $filename, $optionId]);
                    $message = 'Afbeelding succesvol geüpload!';
                } catch (Exception $e) {
                    $error = 'Database fout: ' . $e->getMessage();
                }
            } else {
                $error = 'Fout bij uploaden van afbeelding.';
            }
        }
    }

    // Redirect back to edit page
    if ($laptopId > 0) {
        header('Location: admin.php?panel=1&edit_laptop_config=' . $laptopId . ($message ? '&msg=uploaded' : ''));
        exit;
    }
}

// ============================================================================
// OPTIE AFBEELDING VERWIJDEREN
// ============================================================================
if (isset($_GET['delete_option_image'])) {
    $optionId = (int)$_GET['delete_option_image'];
    $laptopId = (int)($_GET['laptop_id'] ?? 0);

    try {
        $oldImg = $pdo->prepare('SELECT image_path FROM laptop_config_field_options WHERE id = ?');
        $oldImg->execute([$optionId]);
        $oldPath = $oldImg->fetchColumn();

        if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
            unlink(__DIR__ . '/' . $oldPath);
        }

        $stmt = $pdo->prepare('UPDATE laptop_config_field_options SET image_path = NULL WHERE id = ?');
        $stmt->execute([$optionId]);
        $message = 'Afbeelding verwijderd!';
    } catch (Exception $e) {
        $error = 'Fout: ' . $e->getMessage();
    }

    if ($laptopId > 0) {
        header('Location: admin.php?panel=1&edit_laptop_config=' . $laptopId);
        exit;
    }
}

// ============================================================================
// LAPTOP CONFIGURATIE OPSLAAN
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_laptop_config'])) {
    $laptopId = (int)($_POST['laptop_id'] ?? 0);

    if ($laptopId <= 0) {
        $error = 'Ongeldige laptop.';
    } else {
        try {
            ensureLaptopConfigSchema($pdo);

            $defs = getDefaultLaptopConfigFieldDefinitions();

            $pdo->beginTransaction();

            $upsertField = $pdo->prepare(
                'INSERT INTO laptop_config_fields (laptop_id, field_key, field_label, field_type, default_value, is_active, sort_order) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE '
                . 'field_label = VALUES(field_label), '
                . 'field_type = VALUES(field_type), '
                . 'default_value = VALUES(default_value), '
                . 'is_active = VALUES(is_active), '
                . 'sort_order = VALUES(sort_order)'
            );
            $getFieldId = $pdo->prepare('SELECT id FROM laptop_config_fields WHERE laptop_id = ? AND field_key = ?');
            $deleteOptions = $pdo->prepare('DELETE FROM laptop_config_field_options WHERE field_id = ?');
            $insertOption = $pdo->prepare(
                'INSERT INTO laptop_config_field_options (field_id, option_label, option_value, sort_order, is_default) '
                . 'VALUES (?, ?, ?, ?, ?)'
            );

            $fieldLabels = $_POST['field_label'] ?? [];
            $fieldActive = $_POST['field_active'] ?? [];
            $fieldDefaults = $_POST['field_default'] ?? [];
            $fieldOptionsRaw = $_POST['field_options'] ?? [];

            $sort = 1;
            foreach ($defs as $key => $def) {
                $label = trim((string)($fieldLabels[$key] ?? $def['label']));
                if ($label === '') {
                    $label = $def['label'];
                }
                $active = !empty($fieldActive[$key]) ? 1 : 0;
                $type = $def['type'];

                $defaultValue = null;

                if ($type === 'boolean') {
                    $defaultValue = !empty($fieldDefaults[$key]) ? '1' : '0';
                } elseif ($type === 'select') {
                    $raw = (string)($fieldOptionsRaw[$key] ?? '');
                    $lines = preg_split('/\R/u', $raw);
                    $opts = [];
                    foreach ($lines as $line) {
                        $v = trim((string)$line);
                        if ($v === '') {
                            continue;
                        }
                        $opts[] = $v;
                    }
                    if (empty($opts)) {
                        $opts = $def['default_options'] ?? [];
                    }
                    $defaultValue = !empty($opts) ? (string)$opts[0] : null;
                }

                $upsertField->execute([$laptopId, $key, $label, $type, $defaultValue, $active, $sort]);
                $getFieldId->execute([$laptopId, $key]);
                $fieldId = (int)$getFieldId->fetchColumn();

                if ($type === 'select') {
                    $deleteOptions->execute([$fieldId]);

                    $raw = (string)($fieldOptionsRaw[$key] ?? '');
                    $lines = preg_split('/\R/u', $raw);
                    $opts = [];
                    foreach ($lines as $line) {
                        $v = trim((string)$line);
                        if ($v === '') {
                            continue;
                        }
                        $opts[] = $v;
                    }
                    if (empty($opts)) {
                        $opts = $def['default_options'] ?? [];
                    }

                    $o = 1;
                    foreach ($opts as $opt) {
                        $isDefault = ($o === 1) ? 1 : 0;
                        $insertOption->execute([$fieldId, $opt, $opt, $o, $isDefault]);
                        $o++;
                    }
                }

                $sort++;
            }

            $pdo->commit();
            $message = 'Laptop configuratie opgeslagen!';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Fout bij opslaan van laptop configuratie: ' . $e->getMessage();
        }
    }
}

// Laptop verwijderen
if (isset($_GET['delete_laptop'])) {
    $lid = (int)$_GET['delete_laptop'];
    try {
        $pdo->beginTransaction();
        // Verwijder gekoppelde scores eerst om referentiële fouten te voorkomen
        $pdo->prepare('DELETE FROM scores WHERE laptop_id = ?')->execute([$lid]);
        $pdo->prepare('DELETE FROM laptops WHERE id = ?')->execute([$lid]);
        $pdo->commit();
        $message = 'Laptop succesvol verwijderd!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Fout bij verwijderen van laptop: ' . $e->getMessage();
    }
}

// ============================================================================
// MODELNUMMER REGELS BEHEREN
// ============================================================================
// Modelnummer regel toevoegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_model_rule'])) {
    $keyboard = trim($_POST['keyboard_type'] ?? '');
    $wireless = trim($_POST['wireless_type'] ?? '');
    $screen = trim($_POST['screen_type'] ?? '');
    $modelNumber = trim($_POST['model_number'] ?? '');
    $price = isset($_POST['price_eur']) ? floatval($_POST['price_eur']) : 0.0;
    $description = trim($_POST['description'] ?? '');

    if ($keyboard === '' || $wireless === '' || $screen === '' || $modelNumber === '') {
        $error = 'Alle velden behalve beschrijving zijn verplicht!';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO model_number_rules (keyboard_type, wireless_type, screen_type, model_number, price_eur, description) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$keyboard, $wireless, $screen, $modelNumber, $price, $description]);
            $message = 'Modelnummer regel succesvol toegevoegd!';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'Deze combinatie bestaat al!';
            } else {
                $error = 'Fout bij toevoegen: ' . $e->getMessage();
            }
        }
    }
}

// Modelnummer regel bijwerken
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_model_rule'])) {
    $ruleId = (int)($_POST['rule_id'] ?? 0);
    $keyboard = trim($_POST['keyboard_type'] ?? '');
    $wireless = trim($_POST['wireless_type'] ?? '');
    $screen = trim($_POST['screen_type'] ?? '');
    $modelNumber = trim($_POST['model_number'] ?? '');
    $price = isset($_POST['price_eur']) ? floatval($_POST['price_eur']) : 0.0;
    $description = trim($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($keyboard === '' || $wireless === '' || $screen === '' || $modelNumber === '') {
        $error = 'Alle velden behalve beschrijving zijn verplicht!';
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE model_number_rules SET keyboard_type = ?, wireless_type = ?, screen_type = ?, model_number = ?, price_eur = ?, description = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$keyboard, $wireless, $screen, $modelNumber, $price, $description, $isActive, $ruleId]);
            $message = 'Modelnummer regel succesvol bijgewerkt!';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'Deze combinatie bestaat al!';
            } else {
                $error = 'Fout bij bijwerken: ' . $e->getMessage();
            }
        }
    }
}

// Modelnummer regel verwijderen
if (isset($_GET['delete_model_rule'])) {
    $ruleId = (int)$_GET['delete_model_rule'];
    try {
        $stmt = $pdo->prepare('DELETE FROM model_number_rules WHERE id = ?');
        $stmt->execute([$ruleId]);
        $message = 'Modelnummer regel succesvol verwijderd!';
    } catch (Exception $e) {
        $error = 'Fout bij verwijderen: ' . $e->getMessage();
    }
}

// Configuratie optie toevoegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_config_option'])) {
    $optionType = $_POST['option_type'] ?? '';
    $optionValue = trim($_POST['option_value'] ?? '');

    if ($optionType === '' || $optionValue === '') {
        $error = 'Type en waarde zijn verplicht!';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO configuration_options (option_type, option_value, display_order) VALUES (?, ?, 999)');
            $stmt->execute([$optionType, $optionValue]);
            $message = 'Optie succesvol toegevoegd!';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'Deze optie bestaat al!';
            } else {
                $error = 'Fout bij toevoegen: ' . $e->getMessage();
            }
        }
    }
}

// Configuratie optie verwijderen
if (isset($_GET['delete_config_option'])) {
    $optionId = (int)$_GET['delete_config_option'];
    try {
        $stmt = $pdo->prepare('DELETE FROM configuration_options WHERE id = ?');
        $stmt->execute([$optionId]);
        $message = 'Optie succesvol verwijderd!';
    } catch (Exception $e) {
        $error = 'Fout bij verwijderen: ' . $e->getMessage();
    }
}

// ============================================================================
// DATA OPHALEN
// ============================================================================
$laptops = $pdo->query('SELECT id, name, model_code, price_eur FROM laptops WHERE is_active = 1 ORDER BY name')->fetchAll();
$questions = $pdo->query('SELECT id, text, description, weight, display_order FROM questions ORDER BY display_order')->fetchAll();
$adminUsers = $pdo->query('SELECT id, username, created_at FROM admin_users ORDER BY id')->fetchAll();

// Modelnummer data ophalen
$modelRules = $pdo->query('SELECT * FROM model_number_rules ORDER BY keyboard_type, wireless_type, screen_type')->fetchAll();
$configOptions = $pdo->query('SELECT * FROM configuration_options ORDER BY option_type, display_order')->fetchAll();
$keyboardOptions = array_filter($configOptions, fn($o) => $o['option_type'] === 'keyboard');
$wirelessOptions = array_filter($configOptions, fn($o) => $o['option_type'] === 'wireless');
$screenOptions = array_filter($configOptions, fn($o) => $o['option_type'] === 'screen');

$stats = [
    'laptops' => count($laptops),
    'questions' => count($questions),
    'total_scores' => $pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn(),
    'admins' => count($adminUsers),
    'model_rules' => count($modelRules)
];

// Als specifieke vraag geselecteerd voor bewerken
$editQuestion = null;
$editScores = [];
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = ?');
    $stmt->execute([$editId]);
    $editQuestion = $stmt->fetch();
    
    if ($editQuestion) {
        $scoresStmt = $pdo->query("
            SELECT s.id, s.laptop_id, s.option_id, s.points, s.reason,
                   l.name as laptop_name, o.label as option_label
            FROM scores s
            JOIN laptops l ON s.laptop_id = l.id
            JOIN options o ON s.option_id = o.id
            WHERE o.question_id = {$editId}
            ORDER BY l.name, o.display_order
        ");
        $editScores = $scoresStmt->fetchAll();
    }
}

// Als specifieke laptop geselecteerd voor bewerken
$editLaptop = null;
if (isset($_GET['edit_laptop'])) {
    $editId = (int)$_GET['edit_laptop'];
    $stmt = $pdo->prepare('SELECT id, name, model_code, price_eur, is_active FROM laptops WHERE id = ?');
    $stmt->execute([$editId]);
    $editLaptop = $stmt->fetch();
}

// Als specifieke laptop geselecteerd voor score-koppelingen (laptop-centrisch)
$editLaptopScores = null;
$laptopScoreQuestions = [];
if (isset($_GET['edit_laptop_scores'])) {
    $editId = (int)$_GET['edit_laptop_scores'];
    $stmt = $pdo->prepare('SELECT id, name, model_code, price_eur, is_active FROM laptops WHERE id = ?');
    $stmt->execute([$editId]);
    $editLaptopScores = $stmt->fetch();

    if ($editLaptopScores) {
        $sql = <<<SQL
SELECT
    q.id as question_id,
    q.text as question_text,
    q.description as question_description,
    q.weight as question_weight,
    q.display_order,
    o.id as option_id,
    o.label as option_label,
    o.value as option_value,
    o.display_order as option_order,
    s.points as score_points,
    s.reason as score_reason
FROM questions q
INNER JOIN options o ON o.question_id = q.id
LEFT JOIN scores s ON s.option_id = o.id AND s.laptop_id = ?
WHERE q.is_required = 1
ORDER BY q.display_order, o.display_order
SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$editId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $qid = (int)$r['question_id'];
            if (!isset($laptopScoreQuestions[$qid])) {
                $laptopScoreQuestions[$qid] = [
                    'id' => $qid,
                    'text' => $r['question_text'],
                    'description' => $r['question_description'],
                    'weight' => $r['question_weight'],
                    'yes' => null,
                    'no' => null,
                ];
            }

            $opt = [
                'option_id' => (int)$r['option_id'],
                'label' => $r['option_label'],
                'value' => $r['option_value'],
                'points' => (int)($r['score_points'] ?? 0),
                'reason' => (string)($r['score_reason'] ?? ''),
            ];

            if ($opt['value'] === 'yes') {
                $laptopScoreQuestions[$qid]['yes'] = $opt;
            } elseif ($opt['value'] === 'no') {
                $laptopScoreQuestions[$qid]['no'] = $opt;
            }
        }
    }
}

// Als specifieke laptop geselecteerd voor laptop-configuratie
$editLaptopConfig = null;
$editLaptopConfigFields = [];
if (isset($_GET['edit_laptop_config'])) {
    $editId = (int)$_GET['edit_laptop_config'];

    try {
        ensureLaptopConfigSchema($pdo);
        $stmt = $pdo->prepare('SELECT id, name, model_code, price_eur, is_active FROM laptops WHERE id = ?');
        $stmt->execute([$editId]);
        $editLaptopConfig = $stmt->fetch();

        if ($editLaptopConfig) {
            seedLaptopConfigIfMissing($pdo, $editId);

            $fields = $pdo->prepare('SELECT * FROM laptop_config_fields WHERE laptop_id = ? ORDER BY sort_order');
            $fields->execute([$editId]);
            $rows = $fields->fetchAll(PDO::FETCH_ASSOC);

            $optStmt = $pdo->prepare(
                'SELECT o.* FROM laptop_config_field_options o '
                . 'JOIN laptop_config_fields f ON f.id = o.field_id '
                . 'WHERE f.laptop_id = ? ORDER BY f.sort_order, o.sort_order'
            );
            $optStmt->execute([$editId]);
            $opts = $optStmt->fetchAll(PDO::FETCH_ASSOC);

            $optionsByFieldId = [];
            $optionsDetailsByFieldId = [];
            foreach ($opts as $o) {
                $fid = (int)$o['field_id'];
                if (!isset($optionsByFieldId[$fid])) {
                    $optionsByFieldId[$fid] = [];
                    $optionsDetailsByFieldId[$fid] = [];
                }
                $optionsByFieldId[$fid][] = (string)$o['option_label'];
                $optionsDetailsByFieldId[$fid][] = [
                    'id' => (int)$o['id'],
                    'label' => (string)$o['option_label'],
                    'value' => (string)$o['option_value'],
                    'image_path' => $o['image_path'] ?? null,
                ];
            }

            foreach ($rows as $r) {
                $fid = (int)$r['id'];
                $r['options_text'] = '';
                $r['options_details'] = [];
                if (($r['field_type'] ?? '') === 'select') {
                    $list = $optionsByFieldId[$fid] ?? [];
                    $r['options_text'] = implode("\n", $list);
                    $r['options_details'] = $optionsDetailsByFieldId[$fid] ?? [];
                }
                $editLaptopConfigFields[(string)$r['field_key']] = $r;
            }
        }
    } catch (Exception $e) {
        $error = 'Fout bij laden van laptop configuratie: ' . $e->getMessage();
    }
}

// Als specifieke modelnummer regel geselecteerd voor bewerken
$editModelRule = null;
if (isset($_GET['edit_model_rule'])) {
    $editId = (int)$_GET['edit_model_rule'];
    $stmt = $pdo->prepare('SELECT * FROM model_number_rules WHERE id = ?');
    $stmt->execute([$editId]);
    $editModelRule = $stmt->fetch();
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin - Toughbooks</title>
    <link rel="stylesheet" href="assets/css/style.css">
    
</head>
<body>
<header class="site-header">
    <div class="brand">
        <div class="logo">TB</div>
        <div>
            <h1>Admin Dashboard</h1>
            <div class="muted">Beheer vragen, scores en admin gebruikers</div>
        </div>
    </div>
    <div class="admin-actions">
        <div class="user-info">
            <span class="user-badge">👤 <?php echo htmlspecialchars($currentUser['username']); ?></span>
            <a class="muted" href="index.php" target="_blank">🔗 Configurator</a>
            <a class="muted" href="logout.php">Uitloggen</a>
        </div>
    </div>
</header>

<main class="container">
    <?php if ($message): ?>
        <div class="success">✓ <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error">✗ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <section class="hero">
        <h2>Overzicht</h2>
        <p class="muted">Beheer de vragen die gebruikers zien en bepaal welke laptops het beste bij hen passen</p>
    </section>
    
    <div class="admin-grid">
        <div class="stat-card">
            <div class="muted">Actieve Laptops</div>
            <div class="stat-number"><?php echo $stats['laptops']; ?></div>
        </div>
        <div class="stat-card">
            <div class="muted">Vragen</div>
            <div class="stat-number"><?php echo $stats['questions']; ?></div>
        </div>
        <div class="stat-card">
            <div class="muted">Geconfigureerde Scores</div>
            <div class="stat-number"><?php echo $stats['total_scores']; ?></div>
        </div>
        <div class="stat-card">
            <div class="muted">Admin Gebruikers</div>
            <div class="stat-number"><?php echo $stats['admins']; ?></div>
        </div>
    </div>

    <div class="tabs">
        <div class="tab active" onclick="switchTab('vragen')">📝 Vragen Beheren</div>
        <div class="tab" onclick="switchTab('laptops')">💻 Laptops Overzicht</div>
        <div class="tab" onclick="switchTab('modelnummers')">🔢 Modelnummers</div>
        <div class="tab" onclick="switchTab('admins')">👥 Admin Gebruikers</div>
    </div>

    <!-- TAB: Vragen Beheren -->
    <div id="vragen" class="tab-content active">
        <h3>Vragen Beheren</h3>
        
        <div class="info mb-20">
            ℹ️ <strong>Hoe werkt het?</strong> Klanten beantwoorden deze vragen. Op basis van hun antwoorden worden punten toegekend aan laptops. De laptop met de meeste punten wordt als beste match getoond.
        </div>
        
        <button class="cta mb-20" onclick="document.getElementById('addModal').style.display='block'">
            ➕ Nieuwe Vraag Toevoegen
        </button>
        
        <?php if ($editQuestion): ?>
            <div class="edit-section">
                <h4>✏️ Vraag Bewerken</h4>
                <form method="post">
                    <input type="hidden" name="question_id" value="<?php echo $editQuestion['id']; ?>">
                    
                    <div class="form-group">
                        <label>Vraagtekst</label>
                        <input type="text" name="question_text" value="<?php echo htmlspecialchars($editQuestion['text']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Beschrijving (optioneel - extra uitleg voor klant)</label>
                        <textarea name="question_description"><?php echo htmlspecialchars($editQuestion['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Weging (hoe belangrijk is deze vraag?)</label>
                        <input type="number" name="question_weight" step="0.1" min="0.1" max="5" value="<?php echo $editQuestion['weight']; ?>" required>
                        <div class="helper-text">
                            <strong>1.0</strong> = normale vraag | 
                            <strong>1.5</strong> = belangrijke vraag (scores × 1.5) | 
                            <strong>0.5</strong> = minder belangrijk (scores × 0.5)
                        </div>
                    </div>
                    
                    <button type="submit" name="edit_question" class="btn btn-primary">💾 Vraag Opslaan</button>
                    <a href="admin.php?panel=1" class="btn btn-secondary">❌ Annuleren</a>
                </form>
                
                <hr class="hr-custom">
                <h4>🎯 Matches instellen (aanvinken)</h4>
                <p class="muted mb-20">Vink aan bij welke antwoorden een laptop past. Een vinkje telt als match (intern waarde 1), geen vinkje is geen match (0).</p>
                
                <form method="post">
                    <input type="hidden" name="question_id" value="<?php echo $editQuestion['id']; ?>">
                    
                    <div class="score-grid">
                        <?php 
                        $currentLaptop = '';
                        foreach ($editScores as $score): 
                                if ($currentLaptop !== $score['laptop_name']): 
                                if ($currentLaptop !== '') echo '</div>';
                                $currentLaptop = $score['laptop_name'];
                                echo "<h5 class='laptop-heading'>💻 " . htmlspecialchars($currentLaptop) . "</h5>";
                                echo "<div class='two-col-grid'>";
                            endif;
                        ?>
                            <div class="score-item">
                                <strong class="option-label"><?php echo htmlspecialchars($score['option_label']); ?></strong>
                                <div class="two-col-input-grid">
                                    <div>
                                        <label class="small-label">Match:</label>
                                        <input type="hidden" name="scores[<?php echo $score['id']; ?>][match]" value="0">
                                        <label style="display:flex;gap:8px;align-items:center;">
                                            <input type="checkbox" name="scores[<?php echo $score['id']; ?>][match]" value="1" <?php echo ((int)$score['points'] > 0) ? 'checked' : ''; ?>>
                                            <span class="muted">past bij dit antwoord</span>
                                        </label>
                                    </div>
                                    <div>
                                        <label class="small-label">Reden (intern):</label>
                                        <input type="text" name="scores[<?php echo $score['id']; ?>][reason]" value="<?php echo htmlspecialchars($score['reason']); ?>" placeholder="Waarom is dit een match?" class="input-full">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($currentLaptop !== '') echo '</div>'; ?>
                    </div>
                    
                    <div class="info mt-20">
                        💡 <strong>Tip:</strong> Meestal vink je bij “Ja” aan als de laptop die feature heeft. Bij “Nee” vaak uit, maar dat mag je zelf bepalen.
                    </div>
                    
                    <button type="submit" name="update_scores" class="btn btn-primary mt-20">💾 Scores Opslaan</button>
                </form>
            </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th class="col-50">#</th>
                    <th>Vraag</th>
                    <th class="col-100">Weging</th>
                    <th class="col-200">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($questions)): ?>
                <tr>
                    <td colspan="4" class="empty-table">
                        <div class="muted">
                            <div class="emoji-large">📝</div>
                            Nog geen vragen. Klik op "Nieuwe Vraag Toevoegen" om te beginnen.
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($questions as $q): ?>
                    <tr>
                        <td><strong><?php echo $q['display_order']; ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($q['text']); ?></strong>
                                <?php if ($q['description']): ?>
                                <div class="muted muted-small"><?php echo htmlspecialchars($q['description']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="weight-badge"><?php echo $q['weight']; ?>x</span></td>
                        <td>
                            <a href="?panel=1&edit=<?php echo $q['id']; ?>" class="btn btn-secondary btn-small">✏️ Bewerken</a>
                            <a href="?panel=1&delete_question=<?php echo $q['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je deze vraag wilt verwijderen?\n\nAlle gekoppelde scores worden ook verwijderd.');">🗑️ Verwijderen</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TAB: Laptops -->
    <div id="laptops" class="tab-content">
        <h3>Toughbook Modellen</h3>
        <p class="muted">Dit zijn de laptops waar klanten uit kunnen kiezen op basis van hun antwoorden.</p>

        <form method="post" style="margin: 12px 0 20px;">
            <button type="submit" name="repair_mappings" class="btn btn-secondary">
                🔧 Ontbrekende koppelingen repareren
            </button>
            <span class="muted" style="margin-left:8px;">Maakt ontbrekende score-regels aan (laptop × Ja/Nee) zodat je alles kunt invullen.</span>
        </form>

        <button class="cta mb-20" onclick="document.getElementById('addLaptopModal').style.display='block'">
            ➕ Nieuwe Laptop Toevoegen
        </button>

        <?php if ($editLaptop): ?>
            <div class="edit-section">
                <h4>✏️ Laptop Bewerken</h4>
                <form method="post">
                    <input type="hidden" name="laptop_id" value="<?php echo $editLaptop['id']; ?>">

                    <div class="form-group">
                        <label>Naam *</label>
                        <input type="text" name="laptop_name" value="<?php echo htmlspecialchars($editLaptop['name']); ?>" required>
                    </div>

                    

                    <div class="form-group">
                        <label>Prijs (EUR) *</label>
                        <input type="number" name="laptop_price_eur" step="0.01" min="0" value="<?php echo htmlspecialchars(number_format((float)$editLaptop['price_eur'], 2, '.', '')); ?>" required>
                    </div>

                    <div class="form-group">
                        <label><input type="checkbox" name="laptop_is_active" <?php echo !empty($editLaptop['is_active']) ? 'checked' : ''; ?>> Actief</label>
                    </div>

                    <button type="submit" name="update_laptop" class="btn btn-primary">💾 Laptop Opslaan</button>
                    <a href="admin.php?panel=1" class="btn btn-secondary">❌ Annuleren</a>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($editLaptopScores): ?>
            <div class="edit-section">
                <h4>🎯 Scores per vraag voor: <?php echo htmlspecialchars($editLaptopScores['name']); ?></h4>
                <p class="muted mb-20">Kies per vraag of deze laptop beter past bij “Ja” of “Nee”. (Je kunt maar één keuze maken.)</p>

                <?php if (empty($laptopScoreQuestions)): ?>
                    <div class="info">ℹ️ Geen vragen gevonden. Voeg eerst vragen toe in de tab “Vragen Beheren”.</div>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="laptop_id" value="<?php echo (int)$editLaptopScores['id']; ?>">

                        <table>
                            <thead>
                                <tr>
                                    <th>Vraag</th>
                                    <th class="col-250">Keuze</th>
                                    <th class="col-250">Reden (optioneel)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($laptopScoreQuestions as $q): ?>
                                    <?php
                                        $yes = $q['yes'];
                                        $no = $q['no'];
                                        $current = 0;
                                        if ($yes && $yes['points'] > 0) { $current = (int)$yes['option_id']; }
                                        if ($no && $no['points'] > 0) { $current = (int)$no['option_id']; }
                                        $currentReason = '';
                                        if ($yes && $yes['points'] > 0) { $currentReason = (string)$yes['reason']; }
                                        if ($no && $no['points'] > 0) { $currentReason = (string)$no['reason']; }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($q['text']); ?></strong>
                                            <?php if (!empty($q['description'])): ?>
                                                <div class="muted muted-small"><?php echo htmlspecialchars($q['description']); ?></div>
                                            <?php endif; ?>
                                            <div class="muted muted-small">Weging: <?php echo htmlspecialchars((string)$q['weight']); ?>x</div>
                                        </td>
                                        <td>
                                            <input type="hidden" name="choices[<?php echo (int)$q['id']; ?>]" value="0">

                                            <?php if ($yes): ?>
                                                <input type="hidden" name="yes_option[<?php echo (int)$q['id']; ?>]" value="<?php echo (int)$yes['option_id']; ?>">
                                                <label style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                                                    <input type="radio" name="choices[<?php echo (int)$q['id']; ?>]" value="<?php echo (int)$yes['option_id']; ?>" <?php echo ($current === (int)$yes['option_id']) ? 'checked' : ''; ?>>
                                                    <span>Ja</span>
                                                </label>
                                            <?php endif; ?>

                                            <?php if ($no): ?>
                                                <input type="hidden" name="no_option[<?php echo (int)$q['id']; ?>]" value="<?php echo (int)$no['option_id']; ?>">
                                                <label style="display:flex;gap:8px;align-items:center;">
                                                    <input type="radio" name="choices[<?php echo (int)$q['id']; ?>]" value="<?php echo (int)$no['option_id']; ?>" <?php echo ($current === (int)$no['option_id']) ? 'checked' : ''; ?>>
                                                    <span>Nee</span>
                                                </label>
                                            <?php endif; ?>

                                            <?php if (!$yes && !$no): ?>
                                                <span class="muted">(geen Ja/Nee opties)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input type="text" name="reason[<?php echo (int)$q['id']; ?>]" value="<?php echo htmlspecialchars($currentReason); ?>" placeholder="Waarom past deze keuze?" class="input-full">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <button type="submit" name="update_laptop_scores" class="btn btn-primary mt-20">💾 Scores Opslaan</button>
                        <a href="admin.php?panel=1" class="btn btn-secondary mt-20">❌ Sluiten</a>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($editLaptopConfig): ?>
            <?php $defs = getDefaultLaptopConfigFieldDefinitions(); ?>
            <div class="edit-section">
                <h4>⚙️ Laptop instellen: <?php echo htmlspecialchars($editLaptopConfig['name']); ?></h4>
                <p class="muted" style="margin-top:-6px;">
                    👀 Preview: <a href="laptop_configure.php?id=<?php echo (int)$editLaptopConfig['id']; ?>" target="_blank">open gebruikerspagina</a>
                </p>
                <p class="muted mb-20">
                    Vul per optie de mogelijke keuzes in. <strong>De eerste regel</strong> wordt als standaard gebruikt.
                    Zet "Actief" uit als de optie niet geldt voor deze laptop.
                </p>

                <form method="post">
                    <input type="hidden" name="laptop_id" value="<?php echo (int)$editLaptopConfig['id']; ?>">

                    <?php foreach ($defs as $key => $def): ?>
                        <?php
                            $row = $editLaptopConfigFields[$key] ?? null;
                            $labelVal = $row ? (string)$row['field_label'] : (string)$def['label'];
                            $activeVal = $row ? (int)$row['is_active'] : 1;
                            $type = (string)$def['type'];
                            $defaultVal = $row ? (string)($row['default_value'] ?? '') : (string)($def['default_value'] ?? '');
                            $optionsText = $row ? (string)($row['options_text'] ?? '') : implode("\n", (array)($def['default_options'] ?? []));
                        ?>

                        <div class="card" style="margin: 12px 0;">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                                <div style="flex:1;">
                                    <div class="form-group" style="margin:0;">
                                        <label>Label</label>
                                        <input type="text" name="field_label[<?php echo htmlspecialchars($key); ?>]" value="<?php echo htmlspecialchars($labelVal); ?>">
                                    </div>
                                </div>
                                <div>
                                    <label style="display:flex;gap:8px;align-items:center;margin-top:18px;">
                                        <input type="checkbox" name="field_active[<?php echo htmlspecialchars($key); ?>]" value="1" <?php echo ($activeVal ? 'checked' : ''); ?>>
                                        <span>Actief</span>
                                    </label>
                                </div>
                            </div>

                            <?php if ($type === 'select'): ?>
                                <div class="form-group" style="margin-top:10px;">
                                    <label>Keuzes (1 per regel)</label>
                                    <textarea name="field_options[<?php echo htmlspecialchars($key); ?>]" rows="4" placeholder="Bijv. 256GB\n512GB\n1TB"><?php echo htmlspecialchars($optionsText); ?></textarea>
                                </div>

                                <?php if (!empty($row['options_details'])): ?>
                                <div style="margin-top:16px; padding-top:16px; border-top:1px solid #e5e7eb;">
                                    <label style="margin-bottom:12px; display:block;">Afbeeldingen per optie:</label>
                                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:12px;">
                                        <?php foreach ($row['options_details'] as $opt): ?>
                                        <div style="background:#f9fafb; border-radius:8px; padding:12px; border:1px solid #e5e7eb;">
                                            <div style="font-weight:600; margin-bottom:8px;"><?php echo htmlspecialchars($opt['label']); ?></div>
                                            <?php if ($opt['image_path']): ?>
                                                <div style="margin-bottom:8px;">
                                                    <img src="<?php echo htmlspecialchars($opt['image_path']); ?>" alt="<?php echo htmlspecialchars($opt['label']); ?>" style="max-width:100%; max-height:80px; border-radius:4px; object-fit:cover;">
                                                </div>
                                                <a href="?panel=1&delete_option_image=<?php echo $opt['id']; ?>&laptop_id=<?php echo (int)$editLaptopConfig['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Afbeelding verwijderen?');">Verwijderen</a>
                                            <?php else: ?>
                                                <div style="background:#f3f4f6; border:2px dashed #d1d5db; border-radius:6px; padding:16px; text-align:center; margin-bottom:8px;">
                                                    <span style="color:#6b7280; font-size:0.85em;">Geen afbeelding</span>
                                                </div>
                                            <?php endif; ?>
                                            <form method="post" enctype="multipart/form-data" style="margin-top:8px;">
                                                <input type="hidden" name="option_id" value="<?php echo $opt['id']; ?>">
                                                <input type="hidden" name="laptop_id" value="<?php echo (int)$editLaptopConfig['id']; ?>">
                                                <input type="file" name="option_image" accept="image/*" style="font-size:0.8em; width:100%; margin-bottom:6px;">
                                                <button type="submit" name="upload_option_image" class="btn btn-primary btn-small" style="width:100%;">Uploaden</button>
                                            </form>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="form-group" style="margin-top:10px;">
                                    <label style="display:flex;gap:8px;align-items:center;">
                                        <input type="checkbox" name="field_default[<?php echo htmlspecialchars($key); ?>]" value="1" <?php echo ($defaultVal === '1' ? 'checked' : ''); ?>>
                                        <span>Standaard aan</span>
                                    </label>
                                    <div class="helper-text">Op de gebruikerspagina wordt dit een aan/uit optie.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" name="update_laptop_config" class="btn btn-primary mt-20">💾 Configuratie Opslaan</button>
                    <a href="admin.php?panel=1" class="btn btn-secondary mt-20">❌ Sluiten</a>
                </form>
            </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Prijs</th>
                    <th class="col-200">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($laptops as $l): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($l['name']); ?></strong></td>
                    <td><strong>€<?php echo number_format($l['price_eur'], 2, ',', '.'); ?></strong></td>
                    <td>
                        <a href="?panel=1&edit_laptop=<?php echo $l['id']; ?>" class="btn btn-secondary btn-small">✏️ Bewerken</a>
                        <a href="?panel=1&edit_laptop_scores=<?php echo $l['id']; ?>" class="btn btn-primary btn-small">🎯 Scores</a>
                        <a href="?panel=1&edit_laptop_config=<?php echo $l['id']; ?>" class="btn btn-secondary btn-small">⚙️ Instellen</a>
                        <a href="?panel=1&delete_laptop=<?php echo $l['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je deze laptop wilt verwijderen?\n\nAlle gekoppelde scores worden ook verwijderd.');">🗑️ Verwijderen</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- TAB: Modelnummers -->
    <div id="modelnummers" class="tab-content">
        <h3>Modelnummer Systeem</h3>
        <p class="muted">Beheer de mapping tussen configuratieopties en modelnummers. Het modelnummer wordt automatisch bepaald op basis van de geselecteerde opties.</p>

        <div class="info mb-20">
            ℹ️ <strong>Hoe werkt het?</strong><br>
            1. Klanten selecteren een <strong>toetsenbord</strong> (bijv. Qwerty NL of Azerty BE)<br>
            2. Klanten selecteren <strong>draadloze verbindingen</strong> (bijv. WLAN of WLAN + WWAN + 4G + GPS)<br>
            3. Klanten selecteren een <strong>scherm type</strong> (bijv. HD of Full HD + Touchscreen)<br>
            4. Op basis van deze 3 keuzes wordt automatisch het <strong>juiste modelnummer</strong> getoond (bijv. FZ-55JZ011B4)
        </div>

        <div style="background:#fff3cd; border-left:4px solid #ffc107; padding:12px 16px; margin-bottom:20px; border-radius:4px;">
            <strong>💡 Tip:</strong> Voor elk toetsenbord type (Qwerty NL, Azerty BE, etc.) moet je aparte modelnummer regels aanmaken voor alle combinaties van draadloos + scherm.
        </div>

        <div style="display:flex; gap:12px; margin-bottom:20px;">
            <button class="cta" onclick="document.getElementById('addModelRuleModal').style.display='block'">
                ➕ Nieuwe Modelnummer Regel
            </button>
            <button class="btn btn-secondary" onclick="document.getElementById('addConfigOptionModal').style.display='block'">
                ⚙️ Optie Toevoegen
            </button>
        </div>

        <?php if ($editModelRule): ?>
            <div class="edit-section">
                <h4>✏️ Modelnummer Regel Bewerken</h4>
                <form method="post">
                    <input type="hidden" name="rule_id" value="<?php echo $editModelRule['id']; ?>">

                    <div class="form-group">
                        <label>Toetsenbord *</label>
                        <select name="keyboard_type" required>
                            <?php foreach ($keyboardOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt['option_value']); ?>" <?php echo $opt['option_value'] === $editModelRule['keyboard_type'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['option_value']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Draadloze Verbinding *</label>
                        <select name="wireless_type" required>
                            <?php foreach ($wirelessOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt['option_value']); ?>" <?php echo $opt['option_value'] === $editModelRule['wireless_type'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['option_value']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Scherm *</label>
                        <select name="screen_type" required>
                            <?php foreach ($screenOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt['option_value']); ?>" <?php echo $opt['option_value'] === $editModelRule['screen_type'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['option_value']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Modelnummer *</label>
                        <input type="text" name="model_number" value="<?php echo htmlspecialchars($editModelRule['model_number']); ?>" required placeholder="bijv. FZ-55JZ011B4">
                    </div>

                    <div class="form-group">
                        <label>Prijs (EUR)</label>
                        <input type="number" name="price_eur" step="0.01" min="0" value="<?php echo htmlspecialchars(number_format((float)$editModelRule['price_eur'], 2, '.', '')); ?>">
                    </div>

                    <div class="form-group">
                        <label>Beschrijving (optioneel)</label>
                        <textarea name="description"><?php echo htmlspecialchars($editModelRule['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label><input type="checkbox" name="is_active" <?php echo !empty($editModelRule['is_active']) ? 'checked' : ''; ?>> Actief</label>
                    </div>

                    <button type="submit" name="update_model_rule" class="btn btn-primary">💾 Opslaan</button>
                    <a href="admin.php?panel=1" class="btn btn-secondary">❌ Annuleren</a>
                </form>
            </div>
        <?php endif; ?>

        <h4 style="margin-top:24px;">Modelnummer Regels</h4>
        <table>
            <thead>
                <tr>
                    <th>Toetsenbord</th>
                    <th>Draadloos</th>
                    <th>Scherm</th>
                    <th>Modelnummer</th>
                    <th class="col-100">Prijs</th>
                    <th class="col-100">Status</th>
                    <th class="col-200">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($modelRules)): ?>
                <tr>
                    <td colspan="7" class="empty-table">
                        <div class="muted">
                            <div class="emoji-large">🔢</div>
                            Nog geen modelnummer regels. Klik op "Nieuwe Modelnummer Regel" om te beginnen.
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($modelRules as $rule): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($rule['keyboard_type']); ?></td>
                        <td><?php echo htmlspecialchars($rule['wireless_type']); ?></td>
                        <td><?php echo htmlspecialchars($rule['screen_type']); ?></td>
                        <td><strong><?php echo htmlspecialchars($rule['model_number']); ?></strong></td>
                        <td>€<?php echo number_format($rule['price_eur'], 2, ',', '.'); ?></td>
                        <td><?php echo $rule['is_active'] ? '<span style="color:green;">✓ Actief</span>' : '<span style="color:red;">✗ Inactief</span>'; ?></td>
                        <td>
                            <a href="?panel=1&edit_model_rule=<?php echo $rule['id']; ?>" class="btn btn-secondary btn-small">✏️ Bewerken</a>
                            <a href="?panel=1&delete_model_rule=<?php echo $rule['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je deze regel wilt verwijderen?');">🗑️ Verwijderen</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <h4 style="margin-top:32px;">Configuratie Opties Beheren</h4>
        <p class="muted">Deze opties zijn beschikbaar in de dropdowns voor klanten. Voeg hier nieuwe toetsenbord types, draadloze opties of scherm types toe.</p>

        <div style="background:#e3f2fd; border-left:4px solid #2196f3; padding:12px 16px; margin-bottom:16px; border-radius:4px;">
            <strong>📝 Voorbeeld:</strong> Als je een nieuw toetsenbord type toevoegt (bijv. "Qwerty (UK)"), moet je daarna ook nieuwe <strong>Modelnummer Regels</strong> aanmaken voor alle combinaties met dit toetsenbord.
        </div>

        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; margin-top:16px;">
            <!-- Toetsenborden -->
            <div class="card">
                <h5>Toetsenbord Opties</h5>
                <table style="margin-top:12px;">
                    <tbody>
                        <?php foreach ($keyboardOptions as $opt): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($opt['option_value']); ?></td>
                            <td class="col-50">
                                <a href="?panel=1&delete_config_option=<?php echo $opt['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je deze optie wilt verwijderen?');">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Draadloze Verbindingen -->
            <div class="card">
                <h5>Draadloze Verbindingen</h5>
                <table style="margin-top:12px;">
                    <tbody>
                        <?php foreach ($wirelessOptions as $opt): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($opt['option_value']); ?></td>
                            <td class="col-50">
                                <a href="?panel=1&delete_config_option=<?php echo $opt['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je deze optie wilt verwijderen?');">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Schermen -->
            <div class="card">
                <h5>Scherm Opties</h5>
                <table style="margin-top:12px;">
                    <tbody>
                        <?php foreach ($screenOptions as $opt): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($opt['option_value']); ?></td>
                            <td class="col-50">
                                <a href="?panel=1&delete_config_option=<?php echo $opt['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je deze optie wilt verwijderen?');">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB: Admin Gebruikers -->
    <div id="admins" class="tab-content">
        <h3>Admin Gebruikers</h3>
        <p class="muted">Beheer wie toegang heeft tot dit admin panel.</p>
        
        <button class="cta mb-20" onclick="document.getElementById('addAdminModal').style.display='block'">
            ➕ Nieuwe Admin Toevoegen
        </button>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Gebruikersnaam</th>
                    <th>Aangemaakt op</th>
                    <th class="col-250">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adminUsers as $admin): ?>
                <tr class="<?php echo $admin['id'] === $currentUser['id'] ? 'current-user' : ''; ?>">
                    <td><?php echo $admin['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($admin['username']); ?></strong>
                        <?php if ($admin['id'] === $currentUser['id']): ?>
                            <span class="you-badge">● Jij</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d-m-Y H:i', strtotime($admin['created_at'])); ?></td>
                    <td>
                        <button onclick="showPasswordModal(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['username'], ENT_QUOTES); ?>')" class="btn btn-secondary btn-small">
                            🔑 Wachtwoord Wijzigen
                        </button>
                        <?php if ($admin['id'] !== $currentUser['id']): ?>
                            <a href="?panel=1&delete_admin=<?php echo $admin['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je <?php echo htmlspecialchars($admin['username']); ?> wilt verwijderen?');">
                                🗑️ Verwijderen
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal: Nieuwe Vraag -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
        <h3>➕ Nieuwe Vraag Toevoegen</h3>
        
        <div class="info mb-20">
            ℹ️ Na het toevoegen kun je de scores per laptop instellen door op "Bewerken" te klikken.
        </div>
        
        <form method="post">
            <div class="form-group">
                <label>Vraagtekst *</label>
                <input type="text" name="question_text" required placeholder="Bijv. Heeft u GPS nodig op de laptop?">
            </div>
            
            <div class="form-group">
                <label>Beschrijving (optioneel)</label>
                <textarea name="question_description" placeholder="Extra uitleg die onder de vraag verschijnt"></textarea>
            </div>
            
            <div class="form-group">
                <label>Weging *</label>
                <input type="number" name="question_weight" step="0.1" min="0.1" max="5" value="1.0" required>
                <div class="helper-text">
                    <strong>1.0</strong> = normale vraag | <strong>1.5</strong> = belangrijke vraag | <strong>2.0</strong> = zeer belangrijk
                </div>
            </div>
            
            <button type="submit" name="add_question" class="btn btn-primary">💾 Vraag Toevoegen</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Annuleren</button>
        </form>
    </div>
</div>

<!-- Modal: Nieuwe Admin -->
<div id="addAdminModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('addAdminModal').style.display='none'">&times;</span>
        <h3>➕ Nieuwe Admin Toevoegen</h3>
        
        <form method="post">
            <div class="form-group">
                <label>Gebruikersnaam *</label>
                <input type="text" name="admin_username" required placeholder="bijv. john" autocomplete="off">
            </div>
            
            <div class="form-group">
                <label>Wachtwoord *</label>
                <input type="password" name="admin_password" required minlength="6" placeholder="Minimaal 6 tekens" autocomplete="new-password">
            </div>
            
            <div class="form-group">
                <label>Bevestig Wachtwoord *</label>
                <input type="password" name="admin_password_confirm" required minlength="6" placeholder="Herhaal wachtwoord" autocomplete="new-password">
            </div>
            
            <button type="submit" name="add_admin" class="btn btn-primary">💾 Admin Aanmaken</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addAdminModal').style.display='none'">Annuleren</button>
        </form>
    </div>
</div>

<!-- Modal: Wachtwoord Wijzigen -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('passwordModal').style.display='none'">&times;</span>
        <h3 id="passwordModalTitle">🔑 Wachtwoord Wijzigen</h3>
        
        <form method="post">
            <input type="hidden" name="admin_id" id="change_admin_id">
            
            <div class="form-group">
                <label>Nieuw Wachtwoord *</label>
                <input type="password" name="new_password" required minlength="6" placeholder="Minimaal 6 tekens" autocomplete="new-password">
            </div>
            
            <div class="form-group">
                <label>Bevestig Wachtwoord *</label>
                <input type="password" name="confirm_password" required minlength="6" placeholder="Herhaal wachtwoord" autocomplete="new-password">
            </div>
            
            <button type="submit" name="change_password" class="btn btn-primary">💾 Wachtwoord Opslaan</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('passwordModal').style.display='none'">Annuleren</button>
        </form>
    </div>
</div>

<!-- Modal: Nieuwe Laptop -->
<div id="addLaptopModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('addLaptopModal').style.display='none'">&times;</span>
        <h3>➕ Nieuwe Laptop Toevoegen</h3>

        <form method="post">
            <div class="form-group">
                <label>Naam *</label>
                <input type="text" name="laptop_name" required placeholder="Bijv. Toughbook 55">
            </div>

            <div class="form-group">
                <label>Prijs (EUR) *</label>
                <input type="number" name="laptop_price_eur" step="0.01" min="0" value="0.00" required>
            </div>

            <button type="submit" name="add_laptop" class="btn btn-primary">💾 Laptop Toevoegen</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addLaptopModal').style.display='none'">Annuleren</button>
        </form>
    </div>

</div>

<!-- Modal: Nieuwe Modelnummer Regel -->
<div id="addModelRuleModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('addModelRuleModal').style.display='none'">&times;</span>
        <h3>➕ Nieuwe Modelnummer Regel</h3>

        <p class="muted" style="margin-bottom:16px;">
            Selecteer de 3 opties en voer het bijbehorende modelnummer in. Bijvoorbeeld:<br>
            <strong>Azerty (BE)</strong> + <strong>WLAN</strong> + <strong>HD scherm</strong> = <strong>FZ-55G6601Z4</strong>
        </p>

        <form method="post">
            <div class="form-group">
                <label>Toetsenbord *</label>
                <select name="keyboard_type" required>
                    <option value="">-- Selecteer --</option>
                    <?php foreach ($keyboardOptions as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt['option_value']); ?>">
                            <?php echo htmlspecialchars($opt['option_value']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Draadloze Verbinding *</label>
                <select name="wireless_type" required>
                    <option value="">-- Selecteer --</option>
                    <?php foreach ($wirelessOptions as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt['option_value']); ?>">
                            <?php echo htmlspecialchars($opt['option_value']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Scherm *</label>
                <select name="screen_type" required>
                    <option value="">-- Selecteer --</option>
                    <?php foreach ($screenOptions as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt['option_value']); ?>">
                            <?php echo htmlspecialchars($opt['option_value']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Modelnummer *</label>
                <input type="text" name="model_number" required placeholder="bijv. FZ-55JZ011B4">
            </div>

            <div class="form-group">
                <label>Prijs (EUR)</label>
                <input type="number" name="price_eur" step="0.01" min="0" value="0.00">
            </div>

            <div class="form-group">
                <label>Beschrijving (optioneel)</label>
                <textarea name="description" placeholder="Extra informatie over deze configuratie"></textarea>
            </div>

            <button type="submit" name="add_model_rule" class="btn btn-primary">💾 Regel Toevoegen</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModelRuleModal').style.display='none'">Annuleren</button>
        </form>
    </div>
</div>

<!-- Modal: Nieuwe Configuratie Optie -->
<div id="addConfigOptionModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('addConfigOptionModal').style.display='none'">&times;</span>
        <h3>⚙️ Nieuwe Configuratie Optie</h3>

        <form method="post">
            <div class="form-group">
                <label>Type *</label>
                <select name="option_type" required>
                    <option value="">-- Selecteer --</option>
                    <option value="keyboard">Toetsenbord</option>
                    <option value="wireless">Draadloze Verbinding</option>
                    <option value="screen">Scherm</option>
                </select>
            </div>

            <div class="form-group">
                <label>Waarde *</label>
                <input type="text" name="option_value" required placeholder="bijv. Qwerty (UK)">
            </div>

            <button type="submit" name="add_config_option" class="btn btn-primary">💾 Optie Toevoegen</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addConfigOptionModal').style.display='none'">Annuleren</button>
        </form>
    </div>
</div>

<script>
function switchTab(tabName) {
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(t => t.classList.remove('active'));
    contents.forEach(c => c.classList.remove('active'));

    const targetTab = Array.from(tabs).find(t => t.getAttribute('onclick') && t.getAttribute('onclick').includes("'" + tabName + "'"));
    if (targetTab) targetTab.classList.add('active');
    const targetContent = document.getElementById(tabName);
    if (targetContent) targetContent.classList.add('active');
}

function showPasswordModal(adminId, username) {
    document.getElementById('change_admin_id').value = adminId;
    document.getElementById('passwordModalTitle').textContent = '🔑 Wachtwoord Wijzigen voor ' + username;
    document.getElementById('passwordModal').style.display = 'block';
}

window.onclick = function(event) {
    const modals = ['addModal', 'addAdminModal', 'passwordModal', 'addLaptopModal', 'addModelRuleModal', 'addConfigOptionModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target == modal) {
            modal.style.display = "none";
        }
    });
}

// Activeer automatisch de Laptops-tab bij relevante acties
<?php if (isset($_GET['edit_laptop']) || isset($_GET['edit_laptop_scores']) || isset($_GET['edit_laptop_config']) || (isset($_POST['add_laptop']) || isset($_POST['update_laptop']) || isset($_POST['update_laptop_scores']) || isset($_POST['update_laptop_config']) || isset($_POST['repair_mappings']))): ?>
document.addEventListener('DOMContentLoaded', function(){ switchTab('laptops'); });
<?php endif; ?>

// Activeer automatisch de Modelnummers-tab bij relevante acties
<?php if (isset($_GET['edit_model_rule']) || isset($_GET['delete_model_rule']) || isset($_GET['delete_config_option']) || isset($_POST['add_model_rule']) || isset($_POST['update_model_rule']) || isset($_POST['add_config_option'])): ?>
document.addEventListener('DOMContentLoaded', function(){ switchTab('modelnummers'); });
<?php endif; ?>
</script>
</body>
</html>
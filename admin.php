<?php
session_start();
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/ToughbookScorer.php';

Auth::requireLogin();

$pdo = Database::getInstance()->getPdo();
$message = '';
$error = '';
$currentUser = Auth::getCurrentUser();

// Initialiseer ToughbookScorer
$scorer = new ToughbookScorer($pdo, __DIR__ . '/data/toughbooks_configurations.json');

// ============================================================================
// LAAD TOUGHBOOK CONFIGURATIES
// ============================================================================
$configurationsFile = __DIR__ . '/data/toughbooks_configurations.json';
$toughbookConfigs = [];

if (file_exists($configurationsFile)) {
    $jsonData = file_get_contents($configurationsFile);
    $toughbookConfigs = json_decode($jsonData, true) ?? [];
}

// ============================================================================
// LAAD GEWONE DATA VOOR ADMIN (STATS, VRAGEN, LAPTOPS, ADMINS)
// ============================================================================
try {
    $laptops = $pdo->query('SELECT id, name, model_code, price_eur, is_active FROM laptops ORDER BY name')->fetchAll();
    $questions = $pdo->query('SELECT id, text, description, weight, display_order FROM questions ORDER BY display_order')->fetchAll();
    $adminUsers = $pdo->query('SELECT id, username, created_at FROM admin_users ORDER BY id')->fetchAll();
    $stats = [
        'laptops' => count($laptops),
        'questions' => count($questions),
        'total_scores' => (int)$pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn(),
        'admins' => count($adminUsers),
    ];
} catch (Exception $e) {
    $error = 'Fout bij laden van data: ' . $e->getMessage();
    $laptops = $questions = $adminUsers = [];
    $stats = ['laptops' => 0, 'questions' => 0, 'total_scores' => 0, 'admins' => 0];
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
    $specOption = $_POST['spec_option'] ?? 'none';
    $pointsIfMatch = intval($_POST['points_if_match'] ?? 30);
    
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
            
            // Ken scores toe op basis van gekozen spec
            if ($specOption !== 'none') {
                $specMapping = ToughbookScorer::getSpecMapping($specOption);
                if ($specMapping) {
                    $scorer->assignScoresBySpec(
                        $yesId, 
                        $noId, 
                        $specMapping['key'], 
                        $specMapping['value'],
                        $pointsIfMatch,
                        0
                    );
                    $message = "Vraag succesvol toegevoegd! Scores zijn automatisch toegewezen op basis van {$specMapping['key']}.";
                } else {
                    $scorer->assignDefaultScores($yesId, $noId);
                    $message = 'Vraag succesvol toegevoegd met standaard scores.';
                }
            } else {
                $scorer->assignDefaultScores($yesId, $noId);
                $message = 'Vraag succesvol toegevoegd! Je kunt nu de scores handmatig aanpassen door op "Bewerken" te klikken.';
            }
            
            $pdo->commit();
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
            $points = (int)$data['points'];
            $reason = trim($data['reason']);
            
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

// ============================================================================
// LAPTOPS BEHEREN (TOEVOEGEN / BIJWERKEN / VERWIJDEREN)
// ============================================================================
// Laptop toevoegen (ondersteunt: keuze uit configuraties of handmatige invoer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_laptop'])) {
    $price = isset($_POST['laptop_price_eur']) ? floatval($_POST['laptop_price_eur']) : 0.0;

    $isManual = isset($_POST['manual_entry']) && $_POST['manual_entry'] === '1';

    if ($isManual) {
        $modelCode = trim($_POST['manual_model_code'] ?? '');
        $name = trim($_POST['manual_name'] ?? $modelCode);
        $description = trim($_POST['manual_description'] ?? '');
        $specKeys = ['gps','touchscreen','cellular','keyboard','form_factor','display','ram','storage'];
        $manualSpecs = [];
        foreach ($specKeys as $k) {
            if (isset($_POST['manual_' . $k]) && $_POST['manual_' . $k] !== '') {
                $manualSpecs[$k] = trim($_POST['manual_' . $k]);
            }
        }

        if ($modelCode === '') {
            $error = 'Voer een modelcode in voor handmatige toevoeging!';
        } elseif ($price < 0) {
            $error = 'Prijs kan niet negatief zijn!';
        } else {
            try {
                $exists = $pdo->prepare('SELECT id FROM laptops WHERE model_code = ? LIMIT 1');
                $exists->execute([$modelCode]);
                if ($exists->fetch()) {
                    $error = 'Deze modelcode bestaat al in de database.';
                } else {
                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare('INSERT INTO laptops (name, model_code, description, price_eur, is_active) VALUES (?, ?, ?, ?, 1)');
                    $stmt->execute([$name, $modelCode, $description, $price]);
                    $laptopId = $pdo->lastInsertId();
                    if (!empty($manualSpecs)) {
                        $specStmt = $pdo->prepare('INSERT INTO laptop_specs (laptop_id, spec_key, spec_value, spec_type) VALUES (?, ?, ?, ?)');
                        foreach ($manualSpecs as $key => $val) {
                            $specStmt->execute([$laptopId, $key, $val, 'text']);
                        }
                    }

                    $laptopSpecs = $manualSpecs;
                    $questionsStmt = $pdo->query('\
                        SELECT q.id as question_id, q.text as question_text, q.description,\
                               o.id as option_id, o.value as option_value\
                        FROM questions q\
                        JOIN options o ON q.id = o.question_id\
                        ORDER BY q.id, o.display_order\
                    ');
                    $questionsData = $questionsStmt->fetchAll();
                    $scoreStmt = $pdo->prepare('INSERT INTO scores (laptop_id, option_id, points, reason) VALUES (?, ?, ?, ?)');

                    foreach ($questionsData as $qData) {
                        $questionText = strtolower($qData['question_text'] . ' ' . ($qData['description'] ?? ''));
                        $optionValue = $qData['option_value'];
                        $points = 0;
                        $reason = 'Automatisch berekend';

                        if (strpos($questionText, 'gps') !== false) {
                            $hasGPS = isset($laptopSpecs['gps']) && (strtolower($laptopSpecs['gps']) === 'yes' || stripos($laptopSpecs['gps'],'yes')!==false || strtolower($laptopSpecs['gps'])==='1');
                            if ($optionValue === 'yes' && $hasGPS) { $points = 30; $reason = 'Heeft GPS'; }
                            elseif ($optionValue === 'no' && !$hasGPS) { $points = 30; $reason = 'Geen GPS (past bij gebruiker)'; }
                        } elseif (strpos($questionText, 'touch') !== false) {
                            $hasTouch = isset($laptopSpecs['touchscreen']) && (strtolower($laptopSpecs['touchscreen']) === 'yes' || stripos($laptopSpecs['touchscreen'],'yes')!==false);
                            if ($optionValue === 'yes' && $hasTouch) { $points = 30; $reason = 'Heeft touchscreen'; }
                            elseif ($optionValue === 'no' && !$hasTouch) { $points = 30; $reason = 'Geen touchscreen'; }
                        } elseif (strpos($questionText, '4g') !== false || strpos($questionText, '5g') !== false || strpos($questionText, 'mobiel') !== false) {
                            $hasCellular = isset($laptopSpecs['cellular']) && strtolower($laptopSpecs['cellular']) !== 'none' && $laptopSpecs['cellular'] !== '';
                            if ($optionValue === 'yes' && $hasCellular) { $points = 30; $reason = 'Heeft mobiel internet'; }
                            elseif ($optionValue === 'no' && !$hasCellular) { $points = 30; $reason = 'Geen mobiel internet'; }
                        }

                        $scoreStmt->execute([$laptopId, $qData['option_id'], $points, $reason]);
                    }

                    $pdo->commit();
                    $message = "Laptop '$name' succesvol toegevoegd (handmatig)! Scores zijn automatisch berekend op basis van de specs.";
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Fout bij toevoegen van laptop: ' . $e->getMessage();
            }
        }
    } else {
        // config-based
        $modelCode = trim($_POST['laptop_model_code'] ?? '');
        if ($modelCode === '') {
            $error = 'Selecteer een modelcode!';
        } elseif ($price < 0) {
            $error = 'Prijs kan niet negatief zijn!';
        } else {
            try {
                $configFound = null; $modelName = '';
                foreach ($toughbookConfigs as $model => $configs) {
                    foreach ($configs as $config) {
                        if ($config['model_code'] === $modelCode) { $configFound = $config; $modelName = $model; break 2; }
                    }
                }
                if (!$configFound) { $error = 'Modelcode niet gevonden in configuraties!'; }
                else {
                    $exists = $pdo->prepare('SELECT id FROM laptops WHERE model_code = ? LIMIT 1');
                    $exists->execute([$modelCode]);
                    if ($exists->fetch()) { $error = 'Deze modelcode bestaat al in de database.'; }
                    else {
                        $pdo->beginTransaction();
                        $nameParts = [$modelName];
                        if (isset($configFound['specs']['ram'])) $nameParts[] = $configFound['specs']['ram'];
                        if (isset($configFound['specs']['storage'])) $nameParts[] = $configFound['specs']['storage'];
                        if (isset($configFound['specs']['cellular']) && $configFound['specs']['cellular'] !== 'None') $nameParts[] = $configFound['specs']['cellular'];
                        $name = implode(' ', $nameParts);
                        $description = $configFound['description'];
                        $stmt = $pdo->prepare('INSERT INTO laptops (name, model_code, description, price_eur, is_active) VALUES (?, ?, ?, ?, 1)');
                        $stmt->execute([$name, $modelCode, $description, $price]);
                        $laptopId = $pdo->lastInsertId();
                        if (isset($configFound['specs']) && !empty($configFound['specs'])) {
                            $specStmt = $pdo->prepare('INSERT INTO laptop_specs (laptop_id, spec_key, spec_value, spec_type) VALUES (?, ?, ?, ?)');
                            foreach ($configFound['specs'] as $key => $value) $specStmt->execute([$laptopId, $key, $value, 'text']);
                        }
                        $laptopSpecs = $configFound['specs'] ?? [];
                        $questionsStmt = $pdo->query('\
                            SELECT q.id as question_id, q.text as question_text, q.description,\
                                   o.id as option_id, o.value as option_value\
                            FROM questions q\
                            JOIN options o ON q.id = o.question_id\
                            ORDER BY q.id, o.display_order\
                        ');
                        $questionsData = $questionsStmt->fetchAll();
                        $scoreStmt = $pdo->prepare('INSERT INTO scores (laptop_id, option_id, points, reason) VALUES (?, ?, ?, ?)');
                        foreach ($questionsData as $qData) {
                            $questionText = strtolower($qData['question_text'] . ' ' . ($qData['description'] ?? ''));
                            $optionValue = $qData['option_value']; $points = 0; $reason = 'Automatisch berekend';
                            if (strpos($questionText, 'gps') !== false) {
                                $hasGPS = isset($laptopSpecs['gps']) && $laptopSpecs['gps'] === 'Yes';
                                if ($optionValue === 'yes' && $hasGPS) { $points = 30; $reason = 'Laptop heeft GPS'; }
                                elseif ($optionValue === 'no' && !$hasGPS) { $points = 30; $reason = 'Laptop heeft geen GPS (past bij gebruiker)'; }
                            } elseif (strpos($questionText, 'touch') !== false) {
                                $hasTouch = isset($laptopSpecs['touchscreen']) && $laptopSpecs['touchscreen'] === 'Yes';
                                if ($optionValue === 'yes' && $hasTouch) { $points = 30; $reason = 'Laptop heeft touchscreen'; }
                                elseif ($optionValue === 'no' && !$hasTouch) { $points = 30; $reason = 'Laptop heeft geen touchscreen (past bij gebruiker)'; }
                            } elseif (strpos($questionText, '4g') !== false || strpos($questionText, '5g') !== false || strpos($questionText, 'mobiel') !== false) {
                                $hasCellular = isset($laptopSpecs['cellular']) && $laptopSpecs['cellular'] !== 'None';
                                if ($optionValue === 'yes' && $hasCellular) { $points = 30; $reason = 'Laptop heeft mobiel internet (' . $laptopSpecs['cellular'] . ')'; }
                                elseif ($optionValue === 'no' && !$hasCellular) { $points = 30; $reason = 'Laptop heeft geen mobiel internet (past bij gebruiker)'; }
                            }
                            $scoreStmt->execute([$laptopId, $qData['option_id'], $points, $reason]);
                        }
                        $pdo->commit();
                        $message = "Laptop '$name' succesvol toegevoegd! Scores zijn automatisch berekend op basis van de specs.";
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Fout bij toevoegen van laptop: ' . $e->getMessage();
            }
        }
    }
}

$editQuestion = null;
$editScores = [];
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM questions WHERE id = ?');
    $stmt->execute([$editId]);
    $editQuestion = $stmt->fetch();
    
    if ($editQuestion) {
        $scoresStmt = $pdo->query(""
            . "SELECT s.id, s.laptop_id, s.option_id, s.points, s.reason,\n"
            . "       l.name as laptop_name, o.label as option_label\n"
            . "FROM scores s\n"
            . "JOIN laptops l ON s.laptop_id = l.id\n"
            . "JOIN options o ON s.option_id = o.id\n"
            . "WHERE o.question_id = {$editId}\n"
            . "ORDER BY l.name, o.display_order\n"
        );
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
                    <a href="admin.php" class="btn btn-secondary">❌ Annuleren</a>
                </form>
                
                <hr class="hr-custom">
                <h4>🎯 Scores Instellen (hoeveel punten krijgt elke laptop?)</h4>
                <p class="muted mb-20">Bepaal hoeveel punten elke laptop krijgt als de klant "Ja" of "Nee" antwoordt. Hogere punten = betere match.</p>
                
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
                                        <label class="small-label">Punten:</label>
                                        <input type="number" name="scores[<?php echo $score['id']; ?>][points]" value="<?php echo $score['points']; ?>" min="0" max="100" class="input-full">
                                    </div>
                                    <div>
                                        <label class="small-label">Reden (intern):</label>
                                        <input type="text" name="scores[<?php echo $score['id']; ?>][reason]" value="<?php echo htmlspecialchars($score['reason']); ?>" placeholder="Waarom deze punten?" class="input-full">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($currentLaptop !== '') echo '</div>'; ?>
                    </div>
                    
                    <div class="info mt-20">
                        💡 <strong>Tip:</strong> Geef 0 punten als het antwoord niet relevant is. Geef 20-30 punten voor sterke matches. De laptop met de meeste totale punten wint!
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
                            <a href="?edit=<?php echo $q['id']; ?>" class="btn btn-secondary btn-small">✏️ Bewerken</a>
                            <a href="?delete_question=<?php echo $q['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je deze vraag wilt verwijderen?\n\nAlle gekoppelde scores worden ook verwijderd.');">🗑️ Verwijderen</a>
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

        <button class="cta mb-20" onclick="document.getElementById('addLaptopModal').style.display='block'">
            ➕ Nieuwe Laptop Toevoegen
        </button>
        <a class="cta secondary mb-20" href="bulk_import.php">📤 Import CSV/Excel</a>

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
                        <label>Model Code</label>
                        <input type="text" name="laptop_model_code" value="<?php echo htmlspecialchars($editLaptop['model_code'] ?? ''); ?>" readonly>
                        <div class="helper-text">Modelcode kan niet worden gewijzigd na aanmaken.</div>
                    </div>

                    <div class="form-group">
                        <label>Prijs (EUR) *</label>
                        <input type="number" name="laptop_price_eur" step="0.01" min="0" value="<?php echo htmlspecialchars(number_format((float)$editLaptop['price_eur'], 2, '.', '')); ?>" required>
                    </div>

                    <div class="form-group">
                        <label><input type="checkbox" name="laptop_is_active" <?php echo !empty($editLaptop['is_active']) ? 'checked' : ''; ?>> Actief</label>
                    </div>

                    <button type="submit" name="update_laptop" class="btn btn-primary">💾 Laptop Opslaan</button>
                    <a href="admin.php" class="btn btn-secondary">❌ Annuleren</a>
                </form>
            </div>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Model Code</th>
                    <th>Prijs</th>
                    <th class="col-200">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($laptops)): ?>
                <tr>
                    <td colspan="4" class="empty-table">
                        <div class="muted">
                            <div class="emoji-large">💻</div>
                            Nog geen laptops. Klik op "Nieuwe Laptop Toevoegen" om te beginnen.
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($laptops as $l): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($l['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($l['model_code'] ?? '-'); ?></td>
                        <td><strong>€<?php echo number_format($l['price_eur'], 2, ',', '.'); ?></strong></td>
                        <td>
                            <a href="?edit_laptop=<?php echo $l['id']; ?>" class="btn btn-secondary btn-small">✏️ Bewerken</a>
                            <a href="?delete_laptop=<?php echo $l['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je deze laptop wilt verwijderen?\n\nAlle gekoppelde scores worden ook verwijderd.');">🗑️ Verwijderen</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
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
                            <a href="?delete_admin=<?php echo $admin['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je <?php echo htmlspecialchars($admin['username']); ?> wilt verwijderen?');">
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
            ℹ️ Selecteer welke Toughbook-specificatie bij deze vraag hoort. Het systeem wijst automatisch punten toe aan laptops op basis van hun specs!
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
                <label>Welke specificatie hoort bij deze vraag? *</label>
                <select name="spec_option" id="spec_option" required>
                    <?php foreach (ToughbookScorer::getAvailableSpecOptions() as $value => $label): ?>
                        <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="helper-text">
                    <strong>Belangrijk:</strong> Als je bijv. "GPS Module" selecteert, krijgen Toughbooks MET GPS automatisch punten als de klant "Ja" antwoordt.
                </div>
            </div>
            
            <div class="form-group" id="points_group">
                <label>Hoeveel punten bij match?</label>
                <input type="number" name="points_if_match" min="0" max="100" value="30">
                <div class="helper-text">
                    Als een Toughbook de gekozen spec heeft en de klant antwoordt "Ja", krijgt die laptop deze punten.
                </div>
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

<script>
// Toon/verberg punten veld op basis van spec keuze
document.getElementById('spec_option')?.addEventListener('change', function() {
    const pointsGroup = document.getElementById('points_group');
    if (this.value === 'none') {
        pointsGroup.style.display = 'none';
    } else {
        pointsGroup.style.display = 'block';
    }
});
</script>

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

<!-- Modal: Nieuwe Laptop (MET MODELCODE DROPDOWN) -->
<div id="addLaptopModal" class="modal">
    <div class="modal-content modal-wide">
        <span class="close" onclick="document.getElementById('addLaptopModal').style.display='none'">&times;</span>
        <h3>➕ Nieuwe Laptop Toevoegen</h3>

        <div class="info mb-20">
            ℹ️ <strong>Selecteer een modelcode uit de lijst</strong> - de specs worden automatisch uit de configuraties geladen. Scores worden automatisch berekend op basis van de ingestelde vragen!
        </div>

        <form method="post">
            <div class="form-group">
                <label>Toevoegmethode *</label>
                <div>
                    <label style="margin-right:1rem;"><input type="radio" name="entry_mode" value="config" checked> Kies uit configuraties</label>
                    <label><input type="radio" name="entry_mode" value="manual"> Handmatige invoer</label>
                </div>
                <div id="config_select">
                    <select name="laptop_model_code" style="width: 100%; padding: 0.75rem; font-size: 0.95rem; margin-top:0.5rem;">
                        <option value="">-- Selecteer een Toughbook configuratie --</option>
                        <?php foreach ($toughbookConfigs as $modelName => $configs): ?>
                            <optgroup label="<?php echo htmlspecialchars($modelName); ?>">
                                <?php foreach ($configs as $config): ?>
                                    <?php
                                    // Maak een leesbare optie label
                                    $label = $config['model_code'] . ' - ';
                                    $specParts = [];
                                    if (isset($config['specs']['ram'])) $specParts[] = $config['specs']['ram'];
                                    if (isset($config['specs']['storage'])) $specParts[] = $config['specs']['storage'];
                                    if (isset($config['specs']['cellular'])) $specParts[] = $config['specs']['cellular'];
                                    if (isset($config['specs']['gps'])) $specParts[] = 'GPS';
                                    if (isset($config['specs']['touchscreen'])) $specParts[] = 'Touch: ' . $config['specs']['touchscreen'];
                                    if (isset($config['specs']['keyboard'])) $specParts[] = $config['specs']['keyboard'];
                                    if (isset($config['specs']['form_factor'])) $specParts[] = $config['specs']['form_factor'];
                                    $label .= implode(', ', $specParts);
                                    ?>
                                    <option value="<?php echo htmlspecialchars($config['model_code']); ?>">
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="manual_fields" style="display:none; margin-top:0.75rem;">
                    <div class="form-group">
                        <label>Model Code *</label>
                        <input type="text" name="manual_model_code" placeholder="Bijv. CF-33ABC123">
                    </div>
                    <div class="form-group">
                        <label>Naam</label>
                        <input type="text" name="manual_name" placeholder="Leesbare naam (optioneel)">
                    </div>
                    <div class="form-group">
                        <label>Beschrijving</label>
                        <input type="text" name="manual_description" placeholder="Korte omschrijving">
                    </div>
                    <div class="form-group">
                        <label>Specs (optioneel)</label>
                        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.5rem;">
                            <input type="text" name="manual_ram" placeholder="ram (bijv. 16GB)">
                            <input type="text" name="manual_storage" placeholder="storage (bijv. 512GB SSD)">
                            <input type="text" name="manual_cellular" placeholder="cellular (bijv. 4G / None)">
                            <input type="text" name="manual_gps" placeholder="gps (Yes / No)">
                            <input type="text" name="manual_touchscreen" placeholder="touchscreen (Yes / No)">
                            <input type="text" name="manual_keyboard" placeholder="keyboard (AZERTY / QWERTY)">
                            <input type="text" name="manual_form_factor" placeholder="form_factor (Tablet Only / 2-in-1)">
                            <input type="text" name="manual_display" placeholder="display (FHD / HD)">
                        </div>
                    </div>
                </div>
                <div class="helper-text">
                    Selecteer de exacte configuratie. De scores voor vragen worden automatisch berekend op basis van de specs van deze laptop!
                </div>
            </div>

            <div class="form-group">
                <label>Prijs (EUR) *</label>
                <input type="number" name="laptop_price_eur" step="0.01" min="0" value="0.00" required>
            </div>

                        <input type="hidden" name="manual_entry" id="manual_entry" value="0">
                        <button type="submit" name="add_laptop" class="btn btn-primary">💾 Laptop Toevoegen</button>
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('addLaptopModal').style.display='none'">Annuleren</button>
        </form>
    </div>
</div>

<style>
.modal-wide {
    max-width: 800px;
}
</style>

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
    const modals = ['addModal', 'addAdminModal', 'passwordModal', 'addLaptopModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target == modal) {
            modal.style.display = "none";
        }
    });
}

// Toggle manual/config fields
document.querySelectorAll('input[name="entry_mode"]').forEach(function(el){
    el.addEventListener('change', function(){
        const mode = document.querySelector('input[name="entry_mode"]:checked').value;
        document.getElementById('config_select').style.display = (mode === 'config') ? 'block' : 'none';
        document.getElementById('manual_fields').style.display = (mode === 'manual') ? 'block' : 'none';
        document.getElementById('manual_entry').value = (mode === 'manual') ? '1' : '0';
    });
});

// Activeer automatisch de Laptops-tab bij relevante acties
<?php if (isset($_GET['edit_laptop']) || (isset($_POST['add_laptop']) || isset($_POST['update_laptop']))): ?>
document.addEventListener('DOMContentLoaded', function(){ switchTab('laptops'); });
<?php endif; ?>
</script>
</body>
</html>
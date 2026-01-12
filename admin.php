<?php
session_start();
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';

Auth::requireLogin();

$pdo = Database::getInstance()->getPdo();
$message = '';
$error = '';
$currentUser = Auth::getCurrentUser();

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
// Laptop toevoegen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_laptop'])) {
    $name = trim($_POST['laptop_name'] ?? '');
    $modelCode = trim($_POST['laptop_model_code'] ?? '');
    $price = isset($_POST['laptop_price_eur']) ? floatval($_POST['laptop_price_eur']) : 0.0;

    if ($name === '') {
        $error = 'Laptop naam mag niet leeg zijn!';
    } elseif ($price < 0) {
        $error = 'Prijs kan niet negatief zijn!';
    } else {
        try {
            $normalizedModel = ($modelCode !== '') ? $modelCode : null;

            // Validatie: combinatie van naam + model_code moet uniek zijn
            $exists = $pdo->prepare('SELECT id FROM laptops WHERE name = ? AND ( (? IS NULL AND model_code IS NULL) OR model_code = ? ) LIMIT 1');
            $exists->execute([$name, $normalizedModel, $normalizedModel]);
            if ($exists->fetch()) {
                $error = 'Deze combinatie van naam en modelcode bestaat al.';
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('INSERT INTO laptops (name, model_code, price_eur, is_active) VALUES (?, ?, ?, 1)');
                $stmt->execute([$name, $normalizedModel, $price]);
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
                $error = 'Deze combinatie van naam en modelcode bestaat al.';
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
    $modelCode = trim($_POST['laptop_model_code'] ?? '');
    $price = isset($_POST['laptop_price_eur']) ? floatval($_POST['laptop_price_eur']) : 0.0;
    $isActive = isset($_POST['laptop_is_active']) ? 1 : 0;

    if ($name === '') {
        $error = 'Laptop naam mag niet leeg zijn!';
    } elseif ($price < 0) {
        $error = 'Prijs kan niet negatief zijn!';
    } else {
        try {
            $normalizedModel = ($modelCode !== '') ? $modelCode : null;
            // Validatie: combinatie (naam, model_code) mag niet al bestaan bij een andere laptop
            $exists = $pdo->prepare('SELECT id FROM laptops WHERE name = ? AND ( (? IS NULL AND model_code IS NULL) OR model_code = ? ) AND id <> ? LIMIT 1');
            $exists->execute([$name, $normalizedModel, $normalizedModel, $laptopId]);
            if ($exists->fetch()) {
                $error = 'Deze combinatie van naam en modelcode is al in gebruik.';
            } else {
                $stmt = $pdo->prepare('UPDATE laptops SET name = ?, model_code = ?, price_eur = ?, is_active = ? WHERE id = ?');
                $stmt->execute([$name, $normalizedModel, $price, $isActive, $laptopId]);
                $message = 'Laptop succesvol bijgewerkt!';
            }
        } catch (Exception $e) {
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $error = 'Deze combinatie van naam en modelcode is al in gebruik.';
            } else {
                $error = 'Fout bij bijwerken van laptop: ' . $e->getMessage();
            }
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
// DATA OPHALEN
// ============================================================================
$laptops = $pdo->query('SELECT id, name, model_code, price_eur FROM laptops WHERE is_active = 1 ORDER BY name')->fetchAll();
$questions = $pdo->query('SELECT id, text, description, weight, display_order FROM questions ORDER BY display_order')->fetchAll();
$adminUsers = $pdo->query('SELECT id, username, created_at FROM admin_users ORDER BY id')->fetchAll();

$stats = [
    'laptops' => count($laptops),
    'questions' => count($questions),
    'total_scores' => $pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn(),
    'admins' => count($adminUsers)
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
                        <label>Model Code (optioneel)</label>
                        <input type="text" name="laptop_model_code" value="<?php echo htmlspecialchars($editLaptop['model_code'] ?? ''); ?>">
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
                <label>Model Code (optioneel)</label>
                <input type="text" name="laptop_model_code" placeholder="Bijv. FZ-55...">
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

// Activeer automatisch de Laptops-tab bij relevante acties
<?php if (isset($_GET['edit_laptop']) || (isset($_POST['add_laptop']) || isset($_POST['update_laptop']))): ?>
document.addEventListener('DOMContentLoaded', function(){ switchTab('laptops'); });
<?php endif; ?>
</script>
</body>
</html>
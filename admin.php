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
// VRAAG VERWIJDEREN
// ============================================================================
if (isset($_GET['delete_question'])) {
    $qid = (int)$_GET['delete_question'];
    try {
        $pdo->beginTransaction();
        
        // Haal optie IDs op
        $optIds = $pdo->prepare('SELECT id FROM options WHERE question_id = ?');
        $optIds->execute([$qid]);
        $options = $optIds->fetchAll(PDO::FETCH_COLUMN);
        
        // Verwijder scores
        if (!empty($options)) {
            $placeholders = implode(',', array_fill(0, count($options), '?'));
            $delScores = $pdo->prepare("DELETE FROM scores WHERE option_id IN ($placeholders)");
            $delScores->execute($options);
        }
        
        // Verwijder opties
        $delOpts = $pdo->prepare('DELETE FROM options WHERE question_id = ?');
        $delOpts->execute([$qid]);
        
        // Verwijder vraag
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
            
            // Voeg Ja/Nee opties toe
            $optStmt = $pdo->prepare('INSERT INTO options (question_id, label, value, display_order) VALUES (?, ?, ?, ?)');
            $optStmt->execute([$qid, 'Ja', 'yes', 1]);
            $yesId = $pdo->lastInsertId();
            $optStmt->execute([$qid, 'Nee', 'no', 2]);
            $noId = $pdo->lastInsertId();
            
            // Voeg default scores toe
            $laptops = $pdo->query('SELECT id FROM laptops WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
            $scoreStmt = $pdo->prepare('INSERT INTO scores (laptop_id, option_id, points, reason) VALUES (?, ?, ?, ?)');
            foreach ($laptops as $lid) {
                $scoreStmt->execute([$lid, $yesId, 0, 'Standaard score']);
                $scoreStmt->execute([$lid, $noId, 0, 'Niet van toepassing']);
            }
            
            $pdo->commit();
            $message = 'Vraag succesvol toegevoegd!';
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
        $message = 'Scores succesvol bijgewerkt!';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Fout: ' . $e->getMessage();
    }
}

// ============================================================================
// DATA OPHALEN
// ============================================================================
$laptops = $pdo->query('SELECT id, name, model_code, price_eur FROM laptops WHERE is_active = 1 ORDER BY name')->fetchAll();
$questions = $pdo->query('SELECT id, text, description, weight, display_order FROM questions ORDER BY display_order')->fetchAll();
$stats = [
    'laptops' => count($laptops),
    'questions' => count($questions),
    'total_scores' => $pdo->query('SELECT COUNT(*) FROM scores')->fetchColumn()
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
        // Haal scores op
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
            <div class="muted">Beheer alles voor de Toughbooks Configurator</div>
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

    <!-- Statistics -->
    <section class="hero">
        <h2>Overzicht</h2>
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
            <div class="muted">Totale Scores</div>
            <div class="stat-number"><?php echo $stats['total_scores']; ?></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <div class="tab active" onclick="switchTab('vragen')">📝 Vragen Beheren</div>
        <div class="tab" onclick="switchTab('laptops')">💻 Laptops</div>
        <div class="tab" onclick="switchTab('scores')">🎯 Scores Overzicht</div>
    </div>

    <!-- TAB: Vragen Beheren -->
    <div id="vragen" class="tab-content active">
        <h3>Vragen Beheren</h3>
        
        <button class="cta" onclick="document.getElementById('addModal').style.display='block'" style="margin-bottom:20px;">
            ➕ Nieuwe Vraag Toevoegen
        </button>
        
        <?php if ($editQuestion): ?>
            <!-- Edit Mode -->
            <div style="background:rgba(255,255,255,0.05);padding:20px;border-radius:8px;margin-bottom:20px;">
                <h4>✏️ Vraag Bewerken</h4>
                <form method="post">
                    <input type="hidden" name="question_id" value="<?php echo $editQuestion['id']; ?>">
                    
                    <div class="form-group">
                        <label>Vraagtekst</label>
                        <input type="text" name="question_text" value="<?php echo htmlspecialchars($editQuestion['text']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Beschrijving (optioneel)</label>
                        <textarea name="question_description"><?php echo htmlspecialchars($editQuestion['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Weging (belangrijk)</label>
                        <input type="number" name="question_weight" step="0.1" min="0.1" max="5" value="<?php echo $editQuestion['weight']; ?>" required>
                        <div class="helper-text">1.0 = normaal, 1.5 = belangrijker, 0.5 = minder belangrijk</div>
                    </div>
                    
                    <button type="submit" name="edit_question" class="btn btn-primary">💾 Opslaan</button>
                    <a href="admin.php" class="btn btn-secondary">❌ Annuleren</a>
                </form>
                
                <!-- Scores bewerken -->
                <hr style="margin:30px 0;border-color:rgba(255,255,255,0.1);">
                <h4>🎯 Scores voor deze vraag</h4>
                <form method="post">
                    <input type="hidden" name="question_id" value="<?php echo $editQuestion['id']; ?>">
                    
                    <div class="score-grid">
                        <?php 
                        $currentLaptop = '';
                        foreach ($editScores as $score): 
                            if ($currentLaptop !== $score['laptop_name']): 
                                if ($currentLaptop !== '') echo '</div>';
                                $currentLaptop = $score['laptop_name'];
                                echo "<h5 style='margin-top:20px;'>💻 " . htmlspecialchars($currentLaptop) . "</h5>";
                                echo "<div style='display:grid;grid-template-columns:1fr 1fr;gap:12px;'>";
                            endif;
                        ?>
                            <div class="score-item">
                                <strong><?php echo htmlspecialchars($score['option_label']); ?></strong>
                                <div style="display:grid;grid-template-columns:100px 1fr;gap:8px;margin-top:8px;">
                                    <div>
                                        <label style="font-size:0.8em;">Punten:</label>
                                        <input type="number" name="scores[<?php echo $score['id']; ?>][points]" value="<?php echo $score['points']; ?>" style="width:100%;padding:6px;">
                                    </div>
                                    <div>
                                        <label style="font-size:0.8em;">Reden:</label>
                                        <input type="text" name="scores[<?php echo $score['id']; ?>][reason]" value="<?php echo htmlspecialchars($score['reason']); ?>" style="width:100%;padding:6px;">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($currentLaptop !== '') echo '</div>'; ?>
                    </div>
                    
                    <button type="submit" name="update_scores" class="btn btn-primary" style="margin-top:20px;">💾 Scores Opslaan</button>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Vragen Lijst -->
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Vraag</th>
                    <th style="width:100px;">Weging</th>
                    <th style="width:200px;">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $q): ?>
                <tr>
                    <td><?php echo $q['display_order']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($q['text']); ?></strong>
                        <?php if ($q['description']): ?>
                            <div class="muted" style="font-size:0.85em;margin-top:4px;"><?php echo htmlspecialchars($q['description']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $q['weight']; ?>x</td>
                    <td>
                        <a href="?edit=<?php echo $q['id']; ?>" class="btn btn-secondary btn-small">✏️ Bewerken</a>
                        <a href="?delete_question=<?php echo $q['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Weet je zeker dat je deze vraag wilt verwijderen?');">🗑️ Verwijderen</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- TAB: Laptops -->
    <div id="laptops" class="tab-content">
        <h3>Toughbook Modellen</h3>
        <table>
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Model Code</th>
                    <th>Prijs</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($laptops as $l): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($l['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($l['model_code'] ?? '-'); ?></td>
                    <td>€<?php echo number_format($l['price_eur'], 2, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- TAB: Scores Overzicht -->
    <div id="scores" class="tab-content">
        <h3>Scores Overzicht</h3>
        <p class="muted">Overzicht van alle punten per laptop en vraag</p>
        
        <?php
        $scoreOverview = $pdo->query("
            SELECT l.name as laptop, q.text as question, o.label as answer, s.points
            FROM scores s
            JOIN laptops l ON s.laptop_id = l.id
            JOIN options o ON s.option_id = o.id
            JOIN questions q ON o.question_id = q.id
            WHERE s.points > 0
            ORDER BY l.name, q.display_order
        ")->fetchAll();
        
        $grouped = [];
        foreach ($scoreOverview as $row) {
            $grouped[$row['laptop']][] = $row;
        }
        
        foreach ($grouped as $laptop => $scores):
        ?>
            <h4><?php echo htmlspecialchars($laptop); ?></h4>
            <table style="font-size:0.9em;margin-bottom:30px;">
                <thead>
                    <tr>
                        <th>Vraag</th>
                        <th style="width:100px;">Antwoord</th>
                        <th style="width:80px;">Punten</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scores as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['question']); ?></td>
                        <td><strong><?php echo htmlspecialchars($s['answer']); ?></strong></td>
                        <td><strong style="color:#4CAF50;"><?php echo $s['points']; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>
</main>

<!-- Modal: Nieuwe Vraag -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
        <h3>➕ Nieuwe Vraag Toevoegen</h3>
        
        <form method="post">
            <div class="form-group">
                <label>Vraagtekst *</label>
                <input type="text" name="question_text" required placeholder="Bijv. Heeft u GPS nodig?">
            </div>
            
            <div class="form-group">
                <label>Beschrijving (optioneel)</label>
                <textarea name="question_description" placeholder="Extra uitleg voor de gebruiker"></textarea>
            </div>
            
            <div class="form-group">
                <label>Weging *</label>
                <input type="number" name="question_weight" step="0.1" min="0.1" max="5" value="1.0" required>
                <div class="helper-text">
                    <strong>1.0</strong> = normale vraag | 
                    <strong>1.5</strong> = belangrijke vraag | 
                    <strong>0.5</strong> = minder belangrijke vraag
                </div>
            </div>
            
            <button type="submit" name="add_question" class="btn btn-primary">💾 Vraag Toevoegen</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('addModal').style.display='none'">Annuleren</button>
        </form>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.tab-content');
    
    tabs.forEach(t => t.classList.remove('active'));
    contents.forEach(c => c.classList.remove('active'));
    
    // Show selected tab
    event.target.classList.add('active');
    document.getElementById(tabName).classList.add('active');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('addModal');
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>
</body>
</html>
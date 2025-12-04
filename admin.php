 <?php
require_once __DIR__ . '/src/Database.php';

$pdo = Database::getInstance()->getPdo();

// Handle adding a question (simple boolean)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_question'])) {
    $text = trim($_POST['new_question']);
    if ($text !== '') {
        $stmt = $pdo->prepare('INSERT INTO questions (text, type) VALUES (:text, :type)');
        $stmt->execute([':text' => $text, ':type' => 'boolean']);
        $qid = $pdo->lastInsertId();
        $ost = $pdo->prepare('INSERT INTO options (question_id, label, value) VALUES (:qid, :label, :value)');
        $ost->execute([':qid' => $qid, ':label' => 'Ja', ':value' => 'yes']);
        $yesId = $pdo->lastInsertId();
        $ost->execute([':qid' => $qid, ':label' => 'Nee', ':value' => 'no']);
        $noId = $pdo->lastInsertId();
        // Default score 0 for all laptops
        $laptops = $pdo->query('SELECT id FROM laptops')->fetchAll(PDO::FETCH_COLUMN);
        $ins = $pdo->prepare('INSERT INTO scores (laptop_id, option_id, points) VALUES (:lid, :oid, :pts)');
        foreach ($laptops as $lid) {
            $ins->execute([':lid' => $lid, ':oid' => $yesId, ':pts' => 0]);
            $ins->execute([':lid' => $lid, ':oid' => $noId, ':pts' => 0]);
        }
        header('Location: admin.php');
        exit;
    }
}

$laptops = $pdo->query('SELECT id, name FROM laptops')->fetchAll(PDO::FETCH_ASSOC);
$questions = $pdo->query('SELECT id, text FROM questions ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin - Toughbooks configurator</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="brand">
        <div class="logo">TB</div>
        <div>
            <h1>Admin</h1>
            <div class="muted">Beheer laptops en vragen</div>
        </div>
    </div>
    <div class="admin-actions">
        <a class="muted" href="index.php">Bekijk vragenlijst</a>
        <a class="muted" href="init_db.php">Reset DB</a>
    </div>
</header>

<main class="container">
    <section class="hero">
        <h2>Huidige Toughbooks</h2>
        <p class="muted">Op dit moment geregistreerd in het systeem.</p>
    </section>

    <div class="laptop-list">
    <?php foreach ($laptops as $l): ?>
        <article class="card">
            <h3><?php echo htmlspecialchars($l['name']); ?></h3>
            <div class="muted">ID: <?php echo $l['id']; ?></div>
        </article>
    <?php endforeach; ?>
    </div>

    <section style="margin-top:20px">
        <h3>Vragen</h3>
        <div>
        <?php foreach ($questions as $q): ?>
            <div style="margin-bottom:8px" class="muted"><?php echo htmlspecialchars($q['text']); ?></div>
        <?php endforeach; ?>
        </div>

        <h4 style="margin-top:12px">Nieuwe vraag toevoegen</h4>
        <form method="post">
            <label style="display:block">Vraagtekst (Nederlands):</label>
            <input type="text" name="new_question" style="width:60%;padding:8px;border-radius:6px;border:1px solid rgba(255,255,255,0.04)">
            <div style="margin-top:8px">
                <button class="cta" type="submit">Toevoegen</button>
            </div>
        </form>
    </section>
</main>
</body>
</html>

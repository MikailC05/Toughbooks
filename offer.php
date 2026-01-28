<?php
session_start();
require_once __DIR__ . '/src/Database.php';

function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
$offer = $_SESSION['offer'] ?? null;
$laptopId = 0;
$laptop = null;
$specs = [];

// Handle: normal entry to offer page (POST from configurator)
if (!$laptop) {
    if (isset($_POST['laptop_id']) && ctype_digit((string)$_POST['laptop_id'])) {
        $pdo = Database::getInstance()->getPdo();

        $laptopId = (int)$_POST['laptop_id'];
        if ($laptopId <= 0) {
            http_response_code(400);
            echo 'Ongeldige aanvraag.';
            exit;
        }

        $stmt = $pdo->prepare('SELECT id, name, description, model_code, price_eur FROM laptops WHERE id = ? AND is_active = 1');
        $stmt->execute([$laptopId]);
        $laptop = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$laptop) {
            http_response_code(404);
            echo 'Laptop niet gevonden.';
            exit;
        }

        $fieldsStmt = $pdo->prepare('SELECT field_key, field_label, field_type FROM laptop_config_fields WHERE laptop_id = ? AND is_active = 1 ORDER BY sort_order');
        $fieldsStmt->execute([$laptopId]);
        $fieldRows = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);

        $labelByKey = [];
        $typeByKey = [];
        foreach ($fieldRows as $r) {
            $k = (string)$r['field_key'];
            $labelByKey[$k] = (string)$r['field_label'];
            $typeByKey[$k] = (string)$r['field_type'];
        }

        $specs = [];
        foreach ($_POST as $key => $value) {
            if ($key === 'laptop_id') {
                continue;
            }
            if (!isset($labelByKey[$key])) {
                continue;
            }

            $type = $typeByKey[$key] ?? 'select';
            $display = (string)$value;
            if ($type === 'boolean') {
                $display = ((string)$value === '1') ? 'Ja' : 'Nee';
            }

            $specs[] = [
                'key' => $key,
                'label' => $labelByKey[$key],
                'value' => $display,
            ];
        }

        // Bewaar in session voor de download en e-mail
        $_SESSION['offer'] = [
            'laptop_id' => $laptopId,
            'laptop' => $laptop,
            'specs' => $specs,
            'created_at' => date('c'),
        ];
        $offer = $_SESSION['offer'];
    } elseif ($offer && !empty($offer['laptop'])) {
        // Allow refresh/back to this page (use the session)
        $laptop = (array)$offer['laptop'];
        $specs = (array)($offer['specs'] ?? []);
        $laptopId = (int)($offer['laptop_id'] ?? 0);
    } else {
        http_response_code(400);
        echo 'Geen offerte gevonden. Ga terug en configureer opnieuw.';
        exit;
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Overzicht & Offerte</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="brand">
        <div class="logo">TB</div>
        <div>
            <h1>Overzicht</h1>
            <div class="muted"><?php echo h((string)$laptop['name']); ?></div>
        </div>
    </div>
    <div class="top-links">
        <a href="index.php">Home</a>
        <a href="laptop_configure.php?id=<?php echo (int)$laptopId; ?>">Terug</a>
    </div>
</header>

<main class="container">
    <section class="hero" style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;">
        <div>
            <h2 style="margin:0;">Jouw gekozen specs</h2>
            <p style="margin:8px 0 0;">Controleer je configuratie en download de offerte als PDF.</p>
        </div>
        <div style="text-align:right;">
            <div class="muted" style="font-size:0.9em;">Datum</div>
            <div style="font-weight:800;"><?php echo h(date('d-m-Y')); ?></div>
        </div>
    </section>

    <section class="results">
        <article class="card" style="max-width:820px;margin:0 auto;">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                <div>
                    <h3 style="margin:0;"><?php echo h((string)$laptop['name']); ?></h3>
                    <?php if (!empty($laptop['model_code'])): ?>
                        <div class="muted" style="margin-top:6px;">Model: <?php echo h((string)$laptop['model_code']); ?></div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($laptop['price_eur'])): ?>
                    <div style="font-size:1.1em;font-weight:700;">€<?php echo number_format((float)$laptop['price_eur'], 2, ',', '.'); ?></div>
                <?php endif; ?>
            </div>

            <?php if (!empty($laptop['description'])): ?>
                <p style="margin:10px 0 0;" class="muted"><?php echo h((string)$laptop['description']); ?></p>
            <?php endif; ?>

            <div style="margin-top:14px;">
                <strong>Specificaties</strong>
                <?php if (empty($specs)): ?>
                    <div class="muted" style="margin-top:8px;">Geen keuzes ontvangen. Ga terug en maak een selectie.</div>
                <?php else: ?>
                    <div style="margin-top:10px;display:grid;grid-template-columns:1fr;gap:10px;">
                        <?php foreach ($specs as $s): ?>
                            <div style="display:flex;justify-content:space-between;gap:12px;border:1px solid #eef2f7;border-radius:8px;padding:10px 12px;">
                                <div class="muted" style="font-weight:600;"><?php echo h((string)$s['label']); ?></div>
                                <div><?php echo h((string)$s['value']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="wizard-controls" style="margin-top:18px;">
                <a class="cta secondary" href="laptop_configure.php?id=<?php echo (int)$laptopId; ?>">Terug</a>
                <a class="cta" href="offer_download.php">Offerte downloaden (PDF)</a>
            </div>
        </article>
    </section>
</main>
</body>
</html>

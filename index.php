<?php
session_start();
require_once __DIR__ . '/src/Question.php';
require_once __DIR__ . '/src/Configurator.php';

$questions = Question::all();
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = [];
    foreach ($questions as $q) {
        $key = 'q_' . $q->id;
        if (isset($_POST[$key]) && ctype_digit($_POST[$key])) {
            $answers[$q->id] = (int)$_POST[$key];
        }
    }

    if (!empty($answers)) {
        $results = Configurator::score($answers);
        $_SESSION['flash_results'] = $results;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
}

if (isset($_SESSION['flash_results'])) {
    $results = $_SESSION['flash_results'];
    unset($_SESSION['flash_results']);
}

function allScoresZero(?array $results): bool {
    if (empty($results)) {
        return true;
    }
    foreach ($results as $r) {
        if ((float)($r['points'] ?? 0) > 0) {
            return false;
        }
    }
    return true;
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Toughbooks Configurator</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="brand">
        <div class="logo">TB</div>
        <div>
            <h1>Toughbooks Configurator</h1>
            <div class="muted">Vind de beste Toughbook voor jouw werk</div>
        </div>
    </div>
    <div class="top-links">
        <a href="admin.php">Admin</a>
    </div>
</header>

<main class="container">
    <section class="hero">
        <h2>Kies je voorkeuren</h2>
        <p>Beantwoord een paar korte vragen en de configurator geeft de beste Toughbook-opties weer.</p>
    </section>

    <form method="post" id="quizForm">
        <div class="questions" id="questionsContainer">
            <?php $i = 0; foreach ($questions as $q): $i++; ?>
            <fieldset class="step<?php echo $i===1 ? ' visible' : ''; ?>" data-index="<?php echo $i; ?>">
                <legend><?php echo htmlspecialchars($q->text); ?></legend>
                <?php if ($q->description): ?>
                    <p class="muted" style="margin-bottom:12px;font-size:0.9em;"><?php echo htmlspecialchars($q->description); ?></p>
                <?php endif; ?>
                <?php foreach ($q->options as $opt): ?>
                    <label>
                        <input type="radio" name="q_<?php echo $q->id; ?>" value="<?php echo $opt['id']; ?>">
                        <?php echo htmlspecialchars($opt['label']); ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <?php endforeach; ?>
        </div>

        <div class="wizard-controls">
            <button type="button" id="backBtn" class="cta secondary">Terug</button>
            <button type="button" id="nextBtn" class="cta" disabled>Volgende</button>
            <div class="muted" id="wizProgress"></div>
        </div>
    </form>

    <?php if ($results !== null): ?>
    <section class="results">
        <?php $zero = allScoresZero($results); ?>
        <?php if ($zero): ?>
            <div class="info" style="margin-bottom:16px;">
                ℹ️ Er zijn nog geen matches ingesteld. Ga naar <a href="admin.php">Admin</a> en vink per laptop per vraag aan wat past.
            </div>
        <?php endif; ?>

        <?php $best = $results[0] ?? null; ?>
        <?php if ($best): ?>
            <h3>Aanbevolen laptop</h3>
            <article class="card card--clickable" data-href="laptop_configure.php?id=<?php echo (int)$best['id']; ?>" style="border:2px solid rgba(0,0,0,0.08); cursor:pointer;">
                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;margin-bottom:8px;">
                    <h3 style="margin:0;"><?php echo htmlspecialchars($best['name']); ?></h3>
                    <span class="muted"><?php echo round($best['points']); ?> score</span>
                </div>

                <?php if (!empty($best['model_code'])): ?>
                    <div class="muted" style="margin-bottom:8px;">Model: <?php echo htmlspecialchars($best['model_code']); ?></div>
                <?php endif; ?>

                <?php if (!empty($best['description'])): ?>
                    <p style="margin:8px 0;"><?php echo htmlspecialchars($best['description']); ?></p>
                <?php endif; ?>

                <?php if (!empty($best['reasons'])): ?>
                    <div style="margin-top:12px;">
                        <strong style="font-size:0.9em;">Waarom deze laptop:</strong>
                        <ul style="margin:8px 0 0 20px;font-size:0.9em;">
                            <?php foreach (array_slice($best['reasons'], 0, 3) as $reason): ?>
                                <li class="muted"><?php echo htmlspecialchars($reason); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($best['price_eur'])): ?>
                    <div style="margin-top:12px;font-size:1.1em;font-weight:600;">
                        €<?php echo number_format($best['price_eur'], 2, ',', '.'); ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <?php if (!$zero && !empty($results) && count($results) > 1): ?>
            <h3 style="margin-top:20px;">Andere matches</h3>
            <div class="laptop-list">
                <?php foreach (array_slice($results, 1, 3) as $r): ?>
                    <article class="card card--clickable" data-href="laptop_configure.php?id=<?php echo (int)$r['id']; ?>" style="cursor:pointer;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
                            <h3><?php echo htmlspecialchars($r['name']); ?></h3>
                            <span class="muted"><?php echo round($r['points']); ?> score</span>
                        </div>
                        <?php if (!empty($r['price_eur'])): ?>
                            <div style="margin-top:12px;font-size:1.1em;font-weight:600;">
                                €<?php echo number_format($r['price_eur'], 2, ',', '.'); ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>

<script>
(function(){
    const steps = Array.from(document.querySelectorAll('.step'));
    const container = document.getElementById('questionsContainer');
    const backBtn = document.getElementById('backBtn');
    const nextBtn = document.getElementById('nextBtn');
    const progress = document.getElementById('wizProgress');
    const form = document.getElementById('quizForm');
    if(!steps.length) return;

    let current = 0;

    const maxH = Math.max(...steps.map(s => {
        const prev = s.style.display;
        s.style.display = 'block';
        const h = s.scrollHeight;
        s.style.display = prev;
        return h;
    }));
    container.style.height = maxH + 'px';

    function update(){
        steps.forEach((s, idx)=> s.classList.toggle('visible', idx === current));
        backBtn.style.display = current === 0 ? 'none' : 'inline-block';
        const selected = !!steps[current].querySelector('input[type=radio]:checked');
        nextBtn.disabled = !selected;
        nextBtn.textContent = current === steps.length - 1 ? 'Toon beste matches' : 'Volgende';
        progress.textContent = 'Vraag ' + (current+1) + ' van ' + steps.length;
    }

    steps.forEach((step, idx)=>{
        const radios = step.querySelectorAll('input[type=radio]');
        radios.forEach(r => r.addEventListener('change', ()=>{
            if(idx === current) nextBtn.disabled = false;
        }));
    });

    backBtn.addEventListener('click', ()=>{
        if(current > 0) { current--; update(); }
    });

    nextBtn.addEventListener('click', ()=>{
        const any = steps[current].querySelector('input[type=radio]:checked');
        if(!any){ alert('Kies een antwoord om door te gaan.'); return; }
        if(current === steps.length - 1){ form.submit(); return; }
        current++; update();
    });

    update();
})();

(function(){
    document.querySelectorAll('.card--clickable[data-href]').forEach(card => {
        card.addEventListener('click', () => {
            const href = card.getAttribute('data-href');
            if (href) window.location.href = href;
        });
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const href = card.getAttribute('data-href');
                if (href) window.location.href = href;
            }
        });
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'link');
    });
})();
</script>
</body>
</html>
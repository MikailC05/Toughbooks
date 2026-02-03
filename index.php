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

    // Bewaar configuratie
    if (isset($_POST['config_keyboard'])) {
        $_SESSION['config_keyboard'] = trim($_POST['config_keyboard']);
    }
    if (isset($_POST['config_wireless'])) {
        $_SESSION['config_wireless'] = trim($_POST['config_wireless']);
    }
    if (isset($_POST['config_screen'])) {
        $_SESSION['config_screen'] = trim($_POST['config_screen']);
    }
    if (isset($_POST['model_number'])) {
        $_SESSION['model_number'] = trim($_POST['model_number']);
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
</header>

<main class="container">
    <section class="hero" id="heroSection">
        <h2>Kies je voorkeuren</h2>
        <p>Beantwoord een paar korte vragen en de configurator geeft de beste Toughbook-opties weer.</p>
    </section>

    <form method="post" id="quizForm">
        <div class="questions" id="questionsContainer">
            <?php $i = 0; foreach ($questions as $q): $i++; ?>
            <fieldset class="step<?php echo $i===1 ? ' visible' : ''; ?>" data-index="<?php echo $i; ?>" data-type="radio">
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

        <div class="wizard-controls" id="wizardControls" style="position:sticky; top:0; background:white; padding:16px 0; z-index:10; margin-top:-10px;">
            <button type="button" id="backBtn" class="cta secondary">Terug</button>
            <button type="button" id="nextBtn" class="cta" disabled>Volgende</button>
            <div class="muted" id="wizProgress"></div>
        </div>

        <!-- Configuratie sectie (verborgen tot vragen compleet) -->
        <div id="configSection" style="display:none; margin-top:20px;">
            <h2>Configureer jouw Toughbook</h2>
            <p class="muted" style="margin-bottom:24px;">Kies de gewenste configuratie voor jouw Toughbook</p>

            <div style="display:grid; gap:20px; max-width:600px;">
                <div class="form-group">
                    <label style="display:block; font-weight:600; margin-bottom:8px;">Toetsenbordindeling</label>
                    <select id="configKeyboard" name="config_keyboard" style="width:100%; padding:12px; font-size:16px; border:2px solid #ddd; border-radius:6px;">
                        <option value="">-- Selecteer toetsenbord --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="display:block; font-weight:600; margin-bottom:8px;">Draadloze verbindingen</label>
                    <select id="configWireless" name="config_wireless" style="width:100%; padding:12px; font-size:16px; border:2px solid #ddd; border-radius:6px;">
                        <option value="">-- Selecteer verbinding --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label style="display:block; font-weight:600; margin-bottom:8px;">Scherm type</label>
                    <select id="configScreen" name="config_screen" style="width:100%; padding:12px; font-size:16px; border:2px solid #ddd; border-radius:6px;">
                        <option value="">-- Selecteer scherm --</option>
                    </select>
                </div>

                <!-- Modelnummer resultaat -->
                <div id="modelNumberResult" style="display:none; margin-top:16px; padding:12px 16px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; border-radius:6px;">
                    <div style="font-size:0.8em; opacity:0.85; margin-bottom:3px;">Modelnummer:</div>
                    <div id="modelNumberDisplay" style="font-size:1.1em; font-weight:600; letter-spacing:0.5px;">-</div>
                </div>

                <input type="hidden" id="selectedModelNumber" name="model_number" value="">

                <div style="display:flex; gap:12px; margin-top:16px;">
                    <button type="button" id="restartBtn" class="cta secondary">↺ Opnieuw beginnen</button>
                    <button type="submit" id="submitBtn" class="cta" disabled>Toon beste matches</button>
                </div>
            </div>
        </div>
    </form>

    <?php if ($results !== null): ?>
    <section class="results">
        <?php if (isset($_SESSION['model_number']) && !empty($_SESSION['model_number'])): ?>
            <div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:white; padding:14px 18px; border-radius:6px; margin-bottom:20px;">
                <div style="font-size:0.8em; opacity:0.85; margin-bottom:3px;">Jouw modelnummer:</div>
                <div style="font-size:1.2em; font-weight:600; letter-spacing:0.5px; margin-bottom:8px;"><?php echo htmlspecialchars($_SESSION['model_number']); ?></div>
                <?php if (isset($_SESSION['config_keyboard']) || isset($_SESSION['config_wireless']) || isset($_SESSION['config_screen'])): ?>
                    <div style="font-size:0.8em; opacity:0.85; border-top:1px solid rgba(255,255,255,0.2); padding-top:8px; margin-top:8px;">
                        <?php if (isset($_SESSION['config_keyboard'])): ?>
                            <div style="margin-bottom:2px;">• <?php echo htmlspecialchars($_SESSION['config_keyboard']); ?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['config_wireless'])): ?>
                            <div style="margin-bottom:2px;">• <?php echo htmlspecialchars($_SESSION['config_wireless']); ?></div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['config_screen'])): ?>
                            <div>• <?php echo htmlspecialchars($_SESSION['config_screen']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php $zero = allScoresZero($results); ?>
        <?php if ($zero): ?>
            <div class="info" style="margin-bottom:16px;">
                ℹ️ Er zijn nog geen matches ingesteld. Ga naar <a href="admin_login.php">Admin</a> en vink per laptop per vraag aan wat past.
            </div>
        <?php endif; ?>

        <?php $best = $results[0] ?? null; ?>
        <?php if ($best): ?>
            <h3 style="margin-bottom:20px;">Jouw aanbevolen Toughbook</h3>
            <article class="result-card result-card--best card--clickable" data-href="laptop_configure.php?id=<?php echo (int)$best['id']; ?>">
                <div class="result-card__header">
                    <h3 class="result-card__name"><?php echo htmlspecialchars($best['name']); ?></h3>
                    <span class="result-card__score"><?php echo round($best['points']); ?> punten</span>
                </div>

                <?php if (!empty($best['model_code'])): ?>
                    <div class="result-card__model">Model: <?php echo htmlspecialchars($best['model_code']); ?></div>
                <?php endif; ?>

                <?php if (!empty($best['description'])): ?>
                    <p style="margin:8px 0; color: var(--muted);"><?php echo htmlspecialchars($best['description']); ?></p>
                <?php endif; ?>

                <?php if (!empty($best['reasons'])): ?>
                    <div class="result-card__reasons">
                        <div class="result-card__reasons-title">Waarom deze laptop bij jou past:</div>
                        <ul class="result-card__reasons-list">
                            <?php foreach (array_slice($best['reasons'], 0, 4) as $reason): ?>
                                <li><?php echo htmlspecialchars($reason); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($best['price_eur'])): ?>
                    <div class="result-card__price">Vanaf €<?php echo number_format($best['price_eur'], 2, ',', '.'); ?></div>
                <?php endif; ?>

                <a href="laptop_configure.php?id=<?php echo (int)$best['id']; ?>" class="result-card__cta">Configureer deze laptop</a>
            </article>
        <?php endif; ?>

        <?php if (!$zero && !empty($results) && count($results) > 1): ?>
            <h3 style="margin-top:32px;">Andere opties</h3>
            <div class="results-grid">
                <?php foreach (array_slice($results, 1, 3) as $r): ?>
                    <article class="result-card card--clickable" data-href="laptop_configure.php?id=<?php echo (int)$r['id']; ?>">
                        <div class="result-card__header">
                            <h3 class="result-card__name"><?php echo htmlspecialchars($r['name']); ?></h3>
                            <span class="result-card__score"><?php echo round($r['points']); ?> punten</span>
                        </div>
                        <?php if (!empty($r['model_code'])): ?>
                            <div class="result-card__model">Model: <?php echo htmlspecialchars($r['model_code']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($r['reasons']) && count($r['reasons']) > 0): ?>
                            <div style="font-size:0.85em; color: var(--muted); margin-top:8px;">
                                <?php echo htmlspecialchars($r['reasons'][0]); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($r['price_eur'])): ?>
                            <div class="result-card__price">€<?php echo number_format($r['price_eur'], 2, ',', '.'); ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>

<script>
// Configuratie opties laden
(function(){
    fetch('api/model_number_api.php?action=get_options')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const keyboardSelect = document.getElementById('configKeyboard');
                const wirelessSelect = document.getElementById('configWireless');
                const screenSelect = document.getElementById('configScreen');

                // Keyboard options
                data.options.keyboard.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.option_value;
                    option.textContent = opt.option_value;
                    keyboardSelect.appendChild(option);
                });

                // Wireless options
                data.options.wireless.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.option_value;
                    option.textContent = opt.option_value;
                    wirelessSelect.appendChild(option);
                });

                // Screen options
                data.options.screen.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.option_value;
                    option.textContent = opt.option_value;
                    screenSelect.appendChild(option);
                });
            }
        })
        .catch(err => console.error('Error loading config options:', err));
})();

// Modelnummer ophalen bij selectie
(function(){
    const keyboardSelect = document.getElementById('configKeyboard');
    const wirelessSelect = document.getElementById('configWireless');
    const screenSelect = document.getElementById('configScreen');
    const resultDiv = document.getElementById('modelNumberResult');
    const modelDisplay = document.getElementById('modelNumberDisplay');
    const hiddenInput = document.getElementById('selectedModelNumber');
    const submitBtn = document.getElementById('submitBtn');

    function updateModelNumber() {
        const keyboard = keyboardSelect.value;
        const wireless = wirelessSelect.value;
        const screen = screenSelect.value;

        if (keyboard && wireless && screen) {
            const params = new URLSearchParams({
                action: 'get_model_number',
                keyboard: keyboard,
                wireless: wireless,
                screen: screen
            });

            fetch(`api/model_number_api.php?${params}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        modelDisplay.textContent = data.model_number;
                        hiddenInput.value = data.model_number;
                        resultDiv.style.display = 'block';
                        submitBtn.disabled = false;
                    } else {
                        alert(data.error || 'Geen modelnummer gevonden voor deze combinatie');
                        resultDiv.style.display = 'none';
                        hiddenInput.value = '';
                        submitBtn.disabled = true;
                    }
                })
                .catch(err => {
                    console.error('Error fetching model number:', err);
                    resultDiv.style.display = 'none';
                    hiddenInput.value = '';
                    submitBtn.disabled = true;
                });
        } else {
            resultDiv.style.display = 'none';
            hiddenInput.value = '';
            submitBtn.disabled = true;
        }
    }

    keyboardSelect.addEventListener('change', updateModelNumber);
    wirelessSelect.addEventListener('change', updateModelNumber);
    screenSelect.addEventListener('change', updateModelNumber);
})();

// Wizard navigatie
(function(){
    const steps = Array.from(document.querySelectorAll('.step'));
    const container = document.getElementById('questionsContainer');
    const wizardControls = document.getElementById('wizardControls');
    const heroSection = document.getElementById('heroSection');
    const backBtn = document.getElementById('backBtn');
    const nextBtn = document.getElementById('nextBtn');
    const progress = document.getElementById('wizProgress');
    const configSection = document.getElementById('configSection');
    const restartBtn = document.getElementById('restartBtn');
    const form = document.getElementById('quizForm');
    if(!steps.length) return;

    let current = 0;

    // Container krijgt geen vaste hoogte meer, wordt dynamisch per stap
    function updateContainerHeight() {
        const currentStep = steps[current];
        if (currentStep) {
            const height = currentStep.scrollHeight;
            container.style.height = (height + 20) + 'px';
        }
    }

    function isStepComplete(step) {
        return !!step.querySelector('input[type=radio]:checked');
    }

    function showConfigSection() {
        heroSection.style.display = 'none';
        container.style.display = 'none';
        wizardControls.style.display = 'none';
        configSection.style.display = 'block';
    }

    function restartWizard() {
        current = 0;
        heroSection.style.display = 'block';
        container.style.display = 'block';
        wizardControls.style.display = 'block';
        configSection.style.display = 'none';
        update();
    }

    function update(){
        steps.forEach((s, idx)=> s.classList.toggle('visible', idx === current));
        backBtn.style.display = current === 0 ? 'none' : 'inline-block';

        const stepComplete = isStepComplete(steps[current]);
        nextBtn.disabled = !stepComplete;
        nextBtn.textContent = current === steps.length - 1 ? 'Naar configuratie' : 'Volgende';
        progress.textContent = 'Vraag ' + (current+1) + ' van ' + steps.length;
        
        // Update container hoogte voor huidige stap
        updateContainerHeight();
    }

    steps.forEach((step, idx)=>{
        const radios = step.querySelectorAll('input[type=radio]');
        radios.forEach(r => {
            r.addEventListener('change', ()=>{
                if(idx === current) {
                    nextBtn.disabled = false;
                }
            });
            
            // FIX: Check bij page load of er al een waarde geselecteerd is
            if(r.checked && idx === current) {
                nextBtn.disabled = false;
            }
        });
    });

    backBtn.addEventListener('click', ()=>{
        if(current > 0) { current--; update(); }
    });

    nextBtn.addEventListener('click', ()=>{
        if(!isStepComplete(steps[current])){
            alert('Selecteer een optie om door te gaan.');
            return;
        }
        if(current === steps.length - 1){
            showConfigSection();
            return;
        }
        current++; update();
    });

    restartBtn.addEventListener('click', restartWizard);

    // FIX: Update na korte delay om browser autocomplete/restore af te wachten
    setTimeout(update, 50);
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
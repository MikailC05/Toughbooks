<?php
session_start();
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/ModelNumber.php';

$pdo = Database::getInstance()->getPdo();

// Ensure model rules schema
try {
    ModelNumber::ensureSchema($pdo);
} catch (Exception $e) {
    // Non-fatal
}

function ensureLaptopConfigSchema(PDO $pdo): void
{
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
        . "  KEY idx_laptop (laptop_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS laptop_config_field_options (\n"
        . "  id INT(11) NOT NULL AUTO_INCREMENT,\n"
        . "  field_id INT(11) NOT NULL,\n"
        . "  option_label VARCHAR(255) NOT NULL,\n"
        . "  option_value VARCHAR(255) NOT NULL,\n"
        . "  sort_order INT(11) DEFAULT 0,\n"
        . "  is_default TINYINT(1) DEFAULT 0,\n"
        . "  PRIMARY KEY (id),\n"
        . "  KEY idx_field (field_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

$laptopId = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
if ($laptopId <= 0) {
    http_response_code(400);
    echo 'Ongeldige laptop id.';
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

ensureLaptopConfigSchema($pdo);

$fieldsStmt = $pdo->prepare('SELECT * FROM laptop_config_fields WHERE laptop_id = ? AND is_active = 1 ORDER BY sort_order');
$fieldsStmt->execute([$laptopId]);
$fields = $fieldsStmt->fetchAll(PDO::FETCH_ASSOC);

$optionsStmt = $pdo->prepare(
    'SELECT o.* FROM laptop_config_field_options o '
    . 'JOIN laptop_config_fields f ON f.id = o.field_id '
    . 'WHERE f.laptop_id = ? AND f.is_active = 1 ORDER BY f.sort_order, o.sort_order'
);
$optionsStmt->execute([$laptopId]);
$optRows = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);

$optionsByFieldId = [];
foreach ($optRows as $o) {
    $fid = (int)$o['field_id'];
    if (!isset($optionsByFieldId[$fid])) {
        $optionsByFieldId[$fid] = [];
    }
    $optionsByFieldId[$fid][] = $o;
}

// Build 3-step wizard mapping
$stepOrder = [
    1 => ['title' => 'Stap 1 van 3', 'keys' => ['keyboard_layout', 'wireless_connections', 'display_type']],
    2 => ['title' => 'Stap 2 van 3', 'keys' => ['model_variant', 'processor', 'storage', 'ram', 'lte', 'gps', 'battery']],
    3 => ['title' => 'Stap 3 van 3', 'keys' => ['config_area_1', 'config_area_2']],
];

$fieldByKey = [];
foreach ($fields as $f) {
    $fieldByKey[(string)$f['field_key']] = $f;
}

// Assign unknown keys to step 3 (so nothing gets lost)
$known = [];
foreach ($stepOrder as $s) {
    foreach ($s['keys'] as $k) {
        $known[$k] = true;
    }
}
$extraKeys = [];
foreach ($fieldByKey as $k => $_) {
    if (!isset($known[$k])) {
        $extraKeys[] = $k;
    }
}
if (!empty($extraKeys)) {
    $stepOrder[3]['keys'] = array_values(array_merge($stepOrder[3]['keys'], $extraKeys));
}

function normalizeKeyLabel(string $key, string $fallback): string
{
    // Quick nicer titles for the screenshot-like UI
    $map = [
        'keyboard_layout' => 'Toetsenbordindeling',
        'wireless_connections' => 'DRAADLOZE VERBINDINGEN',
        'display_type' => 'HD of FULL HD & TOUCHSCREEN',
        'model_variant' => 'Model variant',
        'processor' => 'Prozessor',
        'storage' => 'Opslag',
        'ram' => 'RAM',
        'lte' => '4G LTE',
        'gps' => 'GPS',
        'battery' => 'Batterij',
        'config_area_1' => 'Configuration area 1',
        'config_area_2' => 'Configuration area 2',
    ];
    return $map[$key] ?? $fallback;
}

$modelRules = [];
try {
    $modelRules = ModelNumber::getRules($pdo, $laptopId);
} catch (Exception $e) {
    $modelRules = [];
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Configureer <?php echo htmlspecialchars($laptop['name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="brand">
        <div class="logo">TB</div>
        <div>
            <h1>Configureer je laptop</h1>
            <div class="muted"><?php echo htmlspecialchars($laptop['name']); ?></div>
        </div>
    </div>
    <div class="top-links">
        <a href="index.php">Terug</a>
        <a href="admin_login.php" class="muted">Admin</a>
    </div>
</header>

<main class="container">
    <h1 class="wizard-title">CONFIGURE YOUR TOUGHBOOK</h1>

    <div class="card" style="max-width:820px;margin:0 auto 14px;">
        <div class="muted" style="font-size:0.9em;">Model nummer:</div>
        <div id="modelNumberPreview" style="font-weight:900;font-size:18px;margin-top:6px;"><?php echo htmlspecialchars((string)($laptop['model_code'] ?? '')); ?></div>
    </div>

    <div class="wizard-stepbar">
        <div class="step-text" id="wizardStepText">Step 1 of 3</div>
        <div class="progress-track"><div class="progress-fill" id="wizardProgress"></div></div>
    </div>

    <?php if (empty($fields)): ?>
        <div class="info">ℹ️ Deze laptop heeft nog geen instelbare opties. Vul dit eerst in via Admin → Laptops → ⚙️ Instellen.</div>
    <?php else: ?>
        <form method="post" onsubmit="return false;">
            <?php foreach ($stepOrder as $stepNum => $stepCfg): ?>
                <section class="wizard-step" data-step="<?php echo (int)$stepNum; ?>">
                    <?php foreach ($stepCfg['keys'] as $key): ?>
                        <?php if (!isset($fieldByKey[$key])) { continue; } ?>
                        <?php
                            $f = $fieldByKey[$key];
                            $fid = (int)$f['id'];
                            $type = (string)$f['field_type'];
                            $label = normalizeKeyLabel((string)$f['field_key'], (string)$f['field_label']);
                            $fieldKey = (string)$f['field_key'];
                            $default = (string)($f['default_value'] ?? '');
                            $opts = $optionsByFieldId[$fid] ?? [];
                        ?>

                        <div class="wizard-section" data-field="<?php echo htmlspecialchars($fieldKey); ?>" data-field-type="<?php echo htmlspecialchars($type); ?>">
                            <h3><?php echo htmlspecialchars($label); ?></h3>

                            <?php if ($type === 'select'): ?>
                                <input type="hidden" name="<?php echo htmlspecialchars($fieldKey); ?>" value="<?php echo htmlspecialchars($default); ?>" data-field-input>
                                <div class="choice-grid">
                                    <?php foreach ($opts as $o): ?>
                                        <?php
                                            $val = (string)$o['option_value'];
                                            $lab = (string)$o['option_label'];
                                            $isSelected = ($val !== '' && $val === $default);
                                        ?>
                                        <button type="button"
                                                class="choice-card <?php echo $isSelected ? 'selected' : ''; ?>"
                                                data-choice
                                                data-value="<?php echo htmlspecialchars($val); ?>">
                                            <span class="dot" aria-hidden="true"></span>
                                            <div class="choice-media" aria-hidden="true">
                                                <?php
                                                    $mediaText = $lab;
                                                    if ($fieldKey === 'processor') { $mediaText = 'CPU'; }
                                                    if ($fieldKey === 'ram') { $mediaText = 'RAM'; }
                                                    if ($fieldKey === 'storage') { $mediaText = 'SSD'; }
                                                    if ($fieldKey === 'battery') { $mediaText = 'BAT'; }
                                                    if ($fieldKey === 'model_variant') { $mediaText = ($lab !== '') ? $lab : 'MODEL'; }
                                                    echo htmlspecialchars($mediaText);
                                                ?>
                                            </div>
                                            <div class="choice-label"><?php echo htmlspecialchars($lab); ?></div>
                                            <?php if (!empty($laptop['model_code']) && $fieldKey === 'model_variant'): ?>
                                                <div class="choice-sub"><?php echo htmlspecialchars($laptop['model_code']); ?></div>
                                            <?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="<?php echo htmlspecialchars($fieldKey); ?>" value="<?php echo ($default === '1') ? '1' : '0'; ?>" data-field-input>
                                <div class="choice-grid">
                                    <?php $yesSelected = ($default === '1'); ?>
                                    <button type="button" class="choice-card <?php echo $yesSelected ? 'selected' : ''; ?>" data-choice data-value="1">
                                        <span class="dot" aria-hidden="true"></span>
                                        <div class="choice-label">Ja</div>
                                        <div class="choice-sub">Inbegrepen</div>
                                    </button>
                                    <button type="button" class="choice-card <?php echo !$yesSelected ? 'selected' : ''; ?>" data-choice data-value="0">
                                        <span class="dot" aria-hidden="true"></span>
                                        <div class="choice-label">Nee</div>
                                        <div class="choice-sub">Niet inbegrepen</div>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>

            <div class="wizard-nav">
                <button type="button" class="cta secondary" id="wizardBack">Terug</button>
                <button type="button" class="cta" id="wizardNext">Volgende</button>
            </div>

            <div class="wizard-summary">
                <div class="info" style="margin-top:16px;">
                    <strong>Samenvatting</strong>
                    <div id="summary" class="muted" style="margin-top:8px;"></div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</main>

<script>
(function(){
    const baseModelNumber = <?php echo json_encode((string)($laptop['model_code'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
    const modelRules = <?php echo json_encode($modelRules, JSON_UNESCAPED_UNICODE); ?>;
    const modelPreview = document.getElementById('modelNumberPreview');

    const steps = Array.from(document.querySelectorAll('.wizard-step[data-step]'));
    const stepText = document.getElementById('wizardStepText');
    const progress = document.getElementById('wizardProgress');
    const backBtn = document.getElementById('wizardBack');
    const nextBtn = document.getElementById('wizardNext');
    const summaryEl = document.getElementById('summary');
    if(!steps.length || !stepText || !progress || !backBtn || !nextBtn || !summaryEl) return;

    const total = steps.length;
    let current = 1;

    function getSelectedByKey(){
        const selected = {};
        document.querySelectorAll('.wizard-section[data-field] input[data-field-input]').forEach(inp => {
            const name = inp.getAttribute('name') || '';
            if(!name) return;
            selected[name] = inp.value || '';
        });
        return selected;
    }

    function computeModelNumber(selectedByKey){
        let best = '';
        let bestSpec = -1;
        let bestSort = -999999;

        (modelRules || []).forEach(rule => {
            if (!rule || rule.is_active !== 1) return;
            const cond = rule.conditions || {};
            for (const k in cond) {
                if (!Object.prototype.hasOwnProperty.call(cond, k)) continue;
                if (!Object.prototype.hasOwnProperty.call(selectedByKey, k)) return;
                if (String(selectedByKey[k]) !== String(cond[k])) return;
            }

            const spec = Object.keys(cond).length;
            const sort = parseInt(rule.sort_order || 0, 10);
            if (spec > bestSpec || (spec === bestSpec && sort > bestSort)) {
                best = String(rule.model_number || '');
                bestSpec = spec;
                bestSort = sort;
            }
        });

        return best || baseModelNumber;
    }

    function updateModelPreview(){
        if(!modelPreview) return;
        const selected = getSelectedByKey();
        modelPreview.textContent = computeModelNumber(selected);
    }

    function updateSummary(){
        const items = [];
        document.querySelectorAll('.wizard-section[data-field]').forEach(section => {
            const key = section.getAttribute('data-field');
            const label = section.querySelector('h3')?.textContent?.trim() || key;
            const input = section.querySelector('input[data-field-input]');
            if(!input) return;

            const type = section.getAttribute('data-field-type');
            const val = input.value;

            if(type === 'boolean'){
                items.push(label + ': ' + (val === '1' ? 'Ja' : 'Nee'));
                return;
            }

            // select: show selected option label if available
            const selectedBtn = section.querySelector('.choice-card.selected[data-choice]');
            const selectedLabel = selectedBtn?.querySelector('.choice-label')?.textContent?.trim();
            items.push(label + ': ' + (selectedLabel || val));
        });

        summaryEl.textContent = items.join(' • ');
        updateModelPreview();
    }

    function showStep(stepNum){
        current = stepNum;
        steps.forEach(s => s.classList.toggle('active', parseInt(s.getAttribute('data-step'), 10) === current));
        stepText.textContent = 'Step ' + current + ' of ' + total;
        progress.style.width = ((current / total) * 100) + '%';
        backBtn.style.visibility = current === 1 ? 'hidden' : 'visible';
        nextBtn.textContent = current === total ? 'Klaar' : 'Volgende';
        updateSummary();
    }

    function selectChoice(section, value){
        const input = section.querySelector('input[data-field-input]');
        if(input) input.value = value;
        section.querySelectorAll('.choice-card[data-choice]').forEach(btn => {
            btn.classList.toggle('selected', btn.getAttribute('data-value') === value);
        });
        updateSummary();
    }

    document.addEventListener('click', (e)=>{
        const btn = e.target.closest('.choice-card[data-choice]');
        if(!btn) return;
        const section = btn.closest('.wizard-section[data-field]');
        if(!section) return;
        const value = btn.getAttribute('data-value') || '';
        selectChoice(section, value);
    });

    backBtn.addEventListener('click', ()=>{
        if(current > 1) showStep(current - 1);
    });

    nextBtn.addEventListener('click', ()=>{
        if(current < total) {
            showStep(current + 1);
        } else {
            // Final step: POST selected configuration to the overview page.
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'offer.php';

            const laptopId = document.createElement('input');
            laptopId.type = 'hidden';
            laptopId.name = 'laptop_id';
            laptopId.value = <?php echo (int)$laptopId; ?>;
            form.appendChild(laptopId);

            document.querySelectorAll('.wizard-section[data-field] input[data-field-input]').forEach(inp => {
                const h = document.createElement('input');
                h.type = 'hidden';
                h.name = inp.getAttribute('name') || '';
                h.value = inp.value || '';
                if (h.name) form.appendChild(h);
            });

            // Computed model number based on rules
            const selected = getSelectedByKey();
            const model = document.createElement('input');
            model.type = 'hidden';
            model.name = 'model_number';
            model.value = computeModelNumber(selected);
            form.appendChild(model);

            document.body.appendChild(form);
            form.submit();
        }
    });

    showStep(1);
    updateModelPreview();
})();
</script>
</body>
</html>

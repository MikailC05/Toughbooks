<?php
session_start();
require_once __DIR__ . '/src/Question.php';
require_once __DIR__ . '/src/Configurator.php';

$questions = Question::all();
$results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// collect selected option ids
	$answers = [];
	foreach ($questions as $q) {
		$key = 'q_' . $q->id;
		if (isset($_POST[$key]) && ctype_digit($_POST[$key])) {
			$answers[$q->id] = (int)$_POST[$key];
		}
	}

	// only compute results if answers were provided
	if (!empty($answers)) {
		$results = Configurator::score($answers);
		// store results in session as a one-time flash and redirect (PRG) so refresh won't resubmit
		$_SESSION['flash_results'] = $results;
		header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
		exit;
	}
}

// On GET, check for flashed results
if (isset($_SESSION['flash_results'])) {
	$results = $_SESSION['flash_results'];
	unset($_SESSION['flash_results']);
}
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Toughbooks configurator - Vragenlijst</title>
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
		<p>Beantwoord een paar korte vragen; de configurator geeft de beste Toughbook-opties weer, afgestemd op jouw gebruik.</p>
	</section>

	<form method="post" id="quizForm">
		<div class="questions" id="questionsContainer">
			<?php $i = 0; foreach ($questions as $q): $i++; ?>
			<fieldset class="step<?php echo $i===1 ? ' visible' : ''; ?>" data-index="<?php echo $i; ?>" data-qid="<?php echo $q->id; ?>">
				<legend><?php echo htmlspecialchars($q->text); ?></legend>
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
		<h3>Beste matches</h3>
		<div class="laptop-list">
			<?php foreach ($results as $r): ?>
				<article class="card">
					<h3><?php echo htmlspecialchars($r['name']); ?></h3>
					<p class="muted" style="margin-top:8px">Bekijk specificaties later — deze optie is het beste passend bij jouw antwoorden.</p>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

</main>

<script>
// Wizard navigation: single-question view with Back/Next controls.
(function(){
	const steps = Array.from(document.querySelectorAll('.step'));
	const container = document.getElementById('questionsContainer');
	const backBtn = document.getElementById('backBtn');
	const nextBtn = document.getElementById('nextBtn');
	const progress = document.getElementById('wizProgress');
	const form = document.getElementById('quizForm');
	if(!steps.length) return;

	let current = 0;

	// compute max height of any step and fix container height so steps don't shift layout
	const maxH = Math.max(...steps.map(s => {
		// ensure element is visible to measure if it isn't
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
		if(!any){ alert('Kies alstublieft een antwoord om door te gaan.'); return; }
		if(current === steps.length - 1){ form.submit(); return; }
		current++; update();
	});

	// initialize
	update();
})();
</script>
</body>
</html>


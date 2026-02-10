<?php
session_start();

$offer = $_SESSION['offer'] ?? null;
if (!$offer || empty($offer['laptop']) || empty($offer['specs'])) {
    http_response_code(400);
    echo 'Geen offerte gevonden in de sessie. Ga terug en klik opnieuw op Klaar.';
    exit;
}

$laptop = $offer['laptop'];
$specs = $offer['specs'];
$modelNumber = (string)($offer['model_number'] ?? '');

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function mimeFromExtension(string $path): string
{
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  return match ($ext) {
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    default => 'application/octet-stream',
  };
}

$filenameBase = 'offerte';
if (!empty($laptop['name'])) {
    $safe = preg_replace('/[^a-z0-9\-]+/i', '-', (string)$laptop['name']);
    $safe = trim($safe, '-');
    if ($safe !== '') {
        $filenameBase = strtolower($safe);
    }
}
$filename = $filenameBase . '-offerte.pdf';

$createdAt = $offer['created_at'] ?? date('c');

// Optional logo for the PDF (place your logo in assets/img/)
$logoDataUri = null;
$logoCandidates = [
  __DIR__ . '/assets/img/logo.png',
  __DIR__ . '/assets/img/logo.jpg',
  __DIR__ . '/assets/img/logo.jpeg',
  __DIR__ . '/assets/img/dmg-logo.png',
];

foreach ($logoCandidates as $candidate) {
  if (is_file($candidate)) {
    $mime = mimeFromExtension($candidate);
    $bytes = @file_get_contents($candidate);
    if ($bytes !== false && $bytes !== '') {
      $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
    break;
  }
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'PDF generator is nog niet geïnstalleerd. Voer uit: composer install';
    exit;
}

require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

ob_start();
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title>Offerte</title>
  <style>
    body{font-family:DejaVu Sans, Arial, sans-serif;margin:0;background:#fff;color:#111827;font-size:12px;}
    .wrap{max-width:900px;margin:24px auto;padding:0 16px;}
    .header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;border-bottom:1px solid #e5e7eb;padding-bottom:12px;}
    .header-left{display:flex;gap:12px;align-items:flex-start;}
    .logo{width:54px;height:54px;object-fit:contain;}
    .brand{font-weight:900;letter-spacing:.5px;font-size:14px;}
    .muted{color:#6b7280;}
    h1{margin:0;font-size:20px;}
    h2{margin:18px 0 8px;font-size:14px;}
    .card{border:1px solid #e5e7eb;border-radius:10px;padding:12px;}
    .grid{display:block;}
    .row{display:flex;justify-content:space-between;gap:12px;border:1px solid #eef2f7;border-radius:10px;padding:9px 10px;margin-bottom:8px;}
    .row .k{font-weight:700;}
    .price{font-weight:900;font-size:16px;}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="header">
      <div class="header-left">
        <?php if ($logoDataUri): ?>
          <img class="logo" src="<?php echo h($logoDataUri); ?>" alt="Logo">
        <?php endif; ?>
        <div>
          <div class="brand">Toughbooks Configurator</div>
          <h1>Offerte</h1>
          <div class="muted">Datum: <?php echo h(date('d-m-Y', strtotime($createdAt))); ?></div>
        </div>
      </div>
      <div style="text-align:right;">
        <?php if (!empty($laptop['price_eur'])): ?>
          <div class="price">€<?php echo number_format((float)$laptop['price_eur'], 2, ',', '.'); ?></div>
          <div class="muted">vanaf prijs</div>
        <?php endif; ?>
      </div>
    </div>

    <h2>Product</h2>
    <div class="card">
      <div style="display:flex;justify-content:space-between;gap:12px;">
        <div>
          <div style="font-weight:900;font-size:14px;"><?php echo h((string)$laptop['name']); ?></div>
          <?php if (!empty($modelNumber) || !empty($laptop['model_code'])): ?>
            <div class="muted" style="margin-top:4px;">Model: <?php echo h((string)($modelNumber !== '' ? $modelNumber : ($laptop['model_code'] ?? ''))); ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!empty($laptop['description'])): ?>
        <div class="muted" style="margin-top:8px;line-height:1.35;"><?php echo h((string)$laptop['description']); ?></div>
      <?php endif; ?>
    </div>

    <h2>Specificaties</h2>
    <div class="grid">
      <?php foreach ($specs as $s): ?>
        <div class="row">
          <div class="k"><?php echo h((string)($s['label'] ?? '')); ?></div>
          <div><?php echo h((string)($s['value'] ?? '')); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
<?php
$html = (string)ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Stream as download
$dompdf->stream($filename, ['Attachment' => true]);
exit;

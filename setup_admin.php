<?php
require_once __DIR__ . '/config.php';

$allow = (Admin::count() === 0);

$message = null;
$error = null;

if (!$allow && !isset($_GET['force'])) {
    $error = 'Er is al een beheerdersaccount ingesteld. Gebruik admin-change of geef ?force=1 om te overschrijven.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($allow || isset($_GET['force']))) {
    $user = isset($_POST['username']) ? trim($_POST['username']) : ADMIN_USER;
    $pass = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
    if ($pass === '') {
        $error = 'Wachtwoord mag niet leeg zijn.';
    } elseif ($pass !== $confirm) {
        $error = 'Bevestiging komt niet overeen.';
    } else {
        if (Admin::create($user, $pass)) {
            $message = 'Admin account aangemaakt. <a href="admin_login.php">Naar login</a>';
        } else {
            $error = 'Kon admin account niet aanmaken. Mogelijk bestaat gebruikersnaam al.';
        }
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Setup admin - Toughbooks</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .box{max-width:520px;margin:40px auto;padding:18px;border-radius:8px;background:#fff;color:#111}
        input[type=text],input[type=password]{width:100%;padding:8px;margin-bottom:12px;border:1px solid #ddd;border-radius:6px}
        .error{color:#b00020;margin-bottom:12px}
        .success{color:#007a1f;margin-bottom:12px}
    </style>
</head>
<body>
<main class="container">
    <section class="hero">
        <h2>Admin account aanmaken</h2>
        <p class="muted">Maak het eerste beheerdersaccount aan (verwijder later admin-setup of verberg file).</p>
    </section>

    <div class="box">
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($message): ?><div class="success"><?php echo $message; ?></div><?php endif; ?>

        <?php if (!$message): ?>
        <form method="post">
            <label>Gebruikersnaam</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars(ADMIN_USER); ?>">
            <label>Wachtwoord</label>
            <input type="password" name="password" required>
            <label>Bevestig wachtwoord</label>
            <input type="password" name="confirm" required>
            <div style="margin-top:10px">
                <button class="cta" type="submit">Maak account</button>
                <a class="muted" style="margin-left:12px" href="index.php">Terug naar site</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>

<?php
require_once __DIR__ . '/config.php';
require_admin();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if ($new === '') {
        $error = 'Nieuw wachtwoord mag niet leeg zijn.';
    } elseif ($new !== $confirm) {
        $error = 'Nieuw wachtwoord en bevestiging komen niet overeen.';
    } elseif (!admin_verify_password($current)) {
        $error = 'Huidig wachtwoord onjuist.';
    } else {
        if (admin_set_password($new)) {
            $success = 'Wachtwoord succesvol gewijzigd.';
        } else {
            $error = 'Kon het wachtwoord niet opslaan. Controleer bestandsrechten.';
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Wachtwoord wijzigen - Toughbooks</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .box{max-width:520px;margin:40px auto;padding:18px;border-radius:8px;background:#fff;color:#111}
        label{display:block;margin-bottom:6px}
        input[type=password]{width:100%;padding:8px;margin-bottom:12px;border:1px solid #ddd;border-radius:6px}
        .error{color:#b00020;margin-bottom:12px}
        .success{color:#007a1f;margin-bottom:12px}
    </style>
</head>
<body>
<main class="container">
    <section class="hero">
        <h2>Wachtwoord wijzigen</h2>
        <p class="muted">Wijzig het beheerderswachtwoord voor de admin account.</p>
    </section>

    <div class="box">
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="post">
            <label>Huidig wachtwoord</label>
            <input type="password" name="current_password" required>

            <label>Nieuw wachtwoord</label>
            <input type="password" name="new_password" required>

            <label>Bevestig nieuw wachtwoord</label>
            <input type="password" name="confirm_password" required>

            <div style="margin-top:10px">
                <button class="cta" type="submit">Wijzig wachtwoord</button>
                <a class="muted" style="margin-left:12px" href="admin.php">Terug naar admin</a>
            </div>
        </form>
    </div>
</main>
</body>
</html>

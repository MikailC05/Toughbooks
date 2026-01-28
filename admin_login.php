<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Auth.php';

$error = '';

function normalizeRedirect(string $redirect): string
{
    $redirect = trim($redirect);
    if ($redirect === '') {
        return 'admin.php?panel=1';
    }

    $decoded = urldecode($redirect);

    // Block absolute URLs / protocol-relative URLs (open redirect)
    if (preg_match('~^(?:https?:)?//~i', $decoded)) {
        return 'admin.php?panel=1';
    }

    // Keep it relative
    $decoded = ltrim($decoded, '/');
    return $decoded === '' ? 'admin.php?panel=1' : $decoded;
}

$redirect = normalizeRedirect((string)($_GET['redirect'] ?? 'admin.php?panel=1'));

// Als al ingelogd, redirect naar de gewenste pagina
if (Auth::isLoggedIn()) {
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        if (Auth::login($username, $password)) {
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Ongeldige gebruikersnaam of wachtwoord.';
        }
    } else {
        $error = 'Vul alle velden in.';
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Login - Toughbooks</title>
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .login-container .form-group label {
            color: black;
        }

        .login-container .form-group input {
            border: 1px solid black;
            color: black;
        }

        .login-container .form-group input:focus {
            outline: none;
            border-color: black;
          
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <div class="logo">TB</div>
            <h2>Admin Login</h2>
            <p class="muted">Toughbooks Configurator</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="username">Gebruikersnaam</label>
                <input type="text" id="username" name="username" required autofocus value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Wachtwoord</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="cta login-btn">Inloggen</button>
        </form>
        
        <div class="back-link">
            <a href="index.php" class="muted">← Terug naar configurator</a>
        </div>
    </div>
</body>
</html>
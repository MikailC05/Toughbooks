<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/Auth.php';

// Als al ingelogd, redirect naar admin
if (Auth::isLoggedIn()) {
    header('Location: admin.php');
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? 'admin.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        if (Auth::login($username, $password)) {
            header('Location: ' . urldecode($redirect));
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
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 40px;
            background: rgba(255,255,255,0.05);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            font-size: 2em;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 6px;
            color: inherit;
            font-size: 1em;
        }
        .form-group input:focus {
            outline: none;
            border-color: rgba(255,255,255,0.3);
        }
        .error-message {
            background: rgba(255,0,0,0.1);
            color: #ff6b6b;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,0,0,0.3);
        }
        .login-btn {
            width: 100%;
            padding: 14px;
            margin-top: 10px;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
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
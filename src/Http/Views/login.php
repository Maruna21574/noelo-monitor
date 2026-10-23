<?php
/** @var string|null $error */
/** @var string $csrfToken */

use Noelo\Monitor\Support\Html;
?>
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Prihlásenie – Noelo Monitor</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-card">
    <div class="logo">
        <img src="https://noelo.sk/img/design/logo-new-v2-sk.png" alt="Noelo.sk">
    </div>
    <div class="subtitle">detský svet</div>
    <div class="badge">Denný monitoring</div>

    <?php if ($error !== null): ?>
        <div class="error">⚠️ <?= Html::e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="?page=login">
        <input type="hidden" name="csrf_token" value="<?= Html::e($csrfToken) ?>">
        <div class="form-group">
            <label>Meno</label>
            <input type="text" name="username" autocomplete="username" placeholder="Zadaj meno" required autofocus>
        </div>
        <div class="form-group">
            <label>Heslo</label>
            <input type="password" name="password" autocomplete="current-password" placeholder="Zadaj heslo" required>
        </div>
        <button type="submit" class="btn-login">Prihlásiť sa</button>
    </form>

    <div class="footer">Interný nástroj &middot; nie je verejne dostupný</div>
</div>
</body>
</html>

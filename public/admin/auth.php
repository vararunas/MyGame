<?php
declare(strict_types=1);
// Configure MYGAME_ADMIN_PASSWORD_HASH with password_hash(..., PASSWORD_DEFAULT).
$hash = (string)(getenv('MYGAME_ADMIN_PASSWORD_HASH') ?: '');
if ($hash === '') {
    http_response_code(503);
    exit('Administratoriaus prieiga nesukonfigūruota. Nustatykite MYGAME_ADMIN_PASSWORD_HASH.');
}
if (isset($_GET['logout'])) {
    unset($_SESSION['mygame_admin']);
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if (password_verify((string)$_POST['admin_password'], $hash)) {
        session_regenerate_id(true);
        $_SESSION['mygame_admin'] = true;
        header('Location: index.php');
        exit;
    }
    $loginError = 'Neteisingas slaptažodis.';
}
if (empty($_SESSION['mygame_admin'])) {
    http_response_code(401);
    ?><!doctype html><html lang="lt"><head><meta charset="utf-8"><title>Administratoriaus prisijungimas</title></head><body><h1>Administratoriaus prisijungimas</h1><?php if (isset($loginError)): ?><p><?=htmlspecialchars($loginError, ENT_QUOTES)?></p><?php endif; ?><form method="post"><label>Slaptažodis <input type="password" name="admin_password" required autocomplete="current-password"></label><button type="submit">Prisijungti</button></form></body></html><?php
    exit;
}

<?php
declare(strict_types=1);
$hash = (string)(getenv('MYGAME_ADMIN_PASSWORD_HASH') ?: '');
$password = '';
$localFile = __DIR__.'/../../config/admin.local.php';
if ($hash === '' && is_file($localFile)) {
    $settings = require $localFile;
    $password = is_array($settings) ? (string)($settings['password'] ?? '') : '';
}
if ($hash === '' && (strlen($password) < 12 || $password === 'PAKEISK_STIPRIU_SLAPTAZODZIU')) {
    http_response_code(503);
    exit('Administratoriaus prieiga nesukonfigūruota. Serveryje sukurkite config/admin.local.php.');
}
$credentialVersion = hash('sha256', $hash !== '' ? $hash : $password);
if (isset($_GET['logout'])) {
    unset($_SESSION['mygame_admin']);
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_password'])) {
    if ($hash !== '' ? password_verify((string)$_POST['admin_password'], $hash) : hash_equals($password, (string)$_POST['admin_password'])) {
        session_regenerate_id(true);
        $_SESSION['mygame_admin'] = $credentialVersion;
        header('Location: index.php');
        exit;
    }
    $loginError = 'Neteisingas slaptažodis.';
}
if (!hash_equals($credentialVersion, (string)($_SESSION['mygame_admin'] ?? ''))) {
    http_response_code(401);
    ?><!doctype html><html lang="lt"><head><meta charset="utf-8"><title>Administratoriaus prisijungimas</title></head><body><h1>Administratoriaus prisijungimas</h1><?php if (isset($loginError)): ?><p><?=htmlspecialchars($loginError, ENT_QUOTES)?></p><?php endif; ?><form method="post"><label>Slaptažodis <input type="password" name="admin_password" required autocomplete="current-password"></label><button type="submit">Prisijungti</button></form></body></html><?php
    exit;
}

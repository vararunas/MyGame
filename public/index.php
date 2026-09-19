<?php
declare(strict_types=1);

session_start();

spl_autoload_register(function (string $class): void {
    $prefix = 'MyGame\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) require $path;
});

$config = require __DIR__ . '/../config/app.php';

use MyGame\Infrastructure\City\CityRepository;

$repository = new CityRepository();
$cities = $repository->all($config['min_city_population']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['city'])) {
    $allowed = array_column(array_map(fn($c) => ['name'=>$c->name], $cities), 'name');
    if (in_array($_POST['city'], $allowed, true)) {
        $_SESSION['start_city'] = $_POST['city'];
        header('Location: company-create.php'); exit;
    }
}

$selected = $_SESSION['start_city'] ?? null;
require __DIR__ . '/../src/Presentation/home.php';

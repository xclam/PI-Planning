<?php
declare(strict_types=1);

use Controllers\PiController;
use Controllers\SprintController;

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
require __DIR__ . '/../app/helpers.php';

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),
]);
session_start();

$debug = (bool) config('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo $debug ? '<pre>' . e((string) $e) . '</pre>' : 'Erreur interne.';
});

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// --- Résolution du chemin (fonctionne avec ou sans /public/ dans l'URL) ---
$path        = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$publicRoot  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$projectRoot = rtrim(str_replace('\\', '/', dirname($publicRoot)), '/');

foreach ([$publicRoot, $projectRoot] as $prefix) {
    if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
        $path = substr($path, strlen($prefix));
        break;
    }
}
$path = '/' . trim($path, '/');

// --- Routes : chemin => méthode HTTP => [contrôleur, action] ---
$routes = [
    '/'            => ['GET'  => [PiController::class, 'index']],
    '/index.php'   => ['GET'  => [PiController::class, 'index']],
    '/pi/create'   => ['GET'  => [PiController::class, 'create']],
    '/pi/store'    => ['POST' => [PiController::class, 'store']],
    '/pi/show'     => ['GET'  => [PiController::class, 'show']],
    '/pi/update'   => ['POST' => [PiController::class, 'update']],
    '/pi/create-us'=> ['POST' => [PiController::class, 'createUs']],
    '/sprint/show' => ['GET'  => [SprintController::class, 'show']],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!isset($routes[$path])) {
    abort(404, 'Page introuvable : ' . $path);
}
if (!isset($routes[$path][$method])) {
    header('Allow: ' . implode(', ', array_keys($routes[$path])));
    abort(405, 'Méthode non autorisée.');
}
if ($method === 'POST' && !csrf_valid()) {
    abort(403, 'Jeton CSRF invalide ou session expirée. Rechargez la page.');
}

[$class, $action] = $routes[$path][$method];
(new $class())->$action();

if ($method === 'GET') {
    unset($_SESSION['_old']);
}
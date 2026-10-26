<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Appel de la config globale
if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return \Core\Config::get($key, $default);
    }
}

// Autoloader natif ultra-léger
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Nettoyage intelligent et robuste de l'URI
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (($pos = strpos($requestUri, '/public/')) !== false) {
    // Si /public/ est dans l'URL, on ne garde que ce qui suit
    $uri = substr($requestUri, $pos + 7);
} else {
    // Sinon, on retire le dossier parent du script (ex: /piplanning)
    $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME']));
    $uri = str_replace($scriptDir, '', $requestUri);
}

$uri = '/' . trim($uri, '/');

// Routage
switch ($uri) {
    case '/':
    case '/index.php':
        (new Controllers\PiController())->index();
        break;
    case '/pi/create':
        (new Controllers\PiController())->create();
        break;
    case '/pi/store':
        (new Controllers\PiController())->store();
        break;
    case '/pi/show':
        (new Controllers\PiController())->show();
        break;
    case '/pi/update':
        (new Controllers\PiController())->update();
        break;
    case '/pi/create-us':
        (new Controllers\PiController())->createUs();
        break;
    case '/sprint/show':
        (new Controllers\SprintController())->show();
        break;
    default:
        http_response_code(404);
        echo "404 Not Found (Route : " . htmlspecialchars($uri) . ")";
        break;
}
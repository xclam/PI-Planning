<?php
declare(strict_types=1);

// Simule une installation sous /piplanning (pour url(), base_path(), asset()…)
$_SERVER['SCRIPT_NAME']   = '/piplanning/public/index.php';
$_SERVER['REQUEST_URI']   = '/piplanning/public/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS']         = '';

// Même autoloader que public/index.php
spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
require __DIR__ . '/../app/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// --- Micro-runner de tests : zéro dépendance -------------------------------
$GLOBALS['__t'] = ['pass' => 0, 'fail' => 0, 'suite' => ''];

function test(string $name, Closure $fn): void
{
    $GLOBALS['__t']['suite'] = $name;
    $fn();
}

function ok(bool $cond, string $label = ''): void
{
    if ($cond) { $GLOBALS['__t']['pass']++; return; }
    $GLOBALS['__t']['fail']++;
    echo '  ÉCHEC — ', $GLOBALS['__t']['suite'], ' : ', $label ?: 'assertion', PHP_EOL;
}

function eq(mixed $expected, mixed $actual, string $label = ''): void
{
    if ($expected === $actual) { $GLOBALS['__t']['pass']++; return; }
    $GLOBALS['__t']['fail']++;
    echo '  ÉCHEC — ', $GLOBALS['__t']['suite'], ' : ', $label ?: 'valeur', PHP_EOL;
    echo '    attendu : ', var_export($expected, true), PHP_EOL;
    echo '    obtenu  : ', var_export($actual, true), PHP_EOL;
}

function results(): never
{
    $t = $GLOBALS['__t'];
    echo PHP_EOL, "{$t['pass']} test(s) OK, {$t['fail']} échec(s).", PHP_EOL;
    exit($t['fail'] > 0 ? 1 : 0);
}
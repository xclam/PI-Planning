<?php
declare(strict_types=1);
/**
 * Enrichissement IA des tickets du sprint actif.
 * Usage   : php bin\enrich-tickets.php [--quiet]
 * Prérequis vérifiés au démarrage : config/config.php, VPN (Jira joignable),
 * BDD joignable, openrouter.api_key et jira.username renseignés.
 * Manque un prérequis → exit 0 immédiat (retentative au prochain passage).
 */

mb_internal_encoding('UTF-8');
$quiet = in_array('--quiet', $argv ?? [], true);
$log = static function (string $m) use ($quiet): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $m;
    if (!$quiet) { echo $line, PHP_EOL; }
    error_log('[pi-planning] ' . $line);
};

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) { require $file; }
});
require __DIR__ . '/../app/helpers.php';

// --- Prérequis 1 : configuration
if (!is_file(__DIR__ . '/../config/config.php')) {
    $log('config/config.php absent : rien à faire.');
    exit(0);
}

// --- Prérequis 2 : Jira joignable (sinon VPN/DNS absent)
$host = parse_url((string) config('jira.base_url', ''), PHP_URL_HOST);
$fp = $host ? @fsockopen((string) $host, 443, $errno, $errstr, 3) : false;
if ($fp === false) {
    $log("skip : " . ($host ?: 'jira.base_url vide') . " injoignable (VPN absent ?");
    exit(0);
}
fclose($fp);

// --- Prérequis 3 : BDD
try {
    Core\Database::getConnection();
} catch (Throwable $e) {
    $log('skip : BDD indisponible (' . $e->getMessage() . ')');
    exit(0);
}

// --- Prérequis 4 : identité et IA configurées
$username = trim((string) config('jira.username', ''));
$openRouter = Core\Factory::openRouter();
if ($username === '' || !$openRouter->isConfigured()) {
    $log('skip : jira.username ou openrouter.api_key non renseigné.');
    exit(0);
}

$projectId = (string) config('jira.project_key', 'B4A');
$budget = (int) config('openrouter.max_calls_per_request', 15);
$done = 0;
$fromCache = 0;

try {
    $jira = Core\Factory::jira();

    foreach ($jira->getBoardSprints((int) config('jira.team_board_id', 4), 'active') as $sprint) {
        foreach ($jira->getSprintIssues($projectId, (int) $sprint['id']) as $issue) {
            $assignee = $issue['fields']['assignee'] ?? [];
            $isMine = false;
            foreach (['name', 'key', 'displayName'] as $field) {
                if (isset($assignee[$field]) && strcasecmp((string) $assignee[$field], $username) === 0) {
                    $isMine = true;
                    break;
                }
            }
            if (!$isMine) { continue; }

            $key = (string) $issue['key'];
            $updated = (string) ($issue['fields']['updated'] ?? '');

            if ($updated !== '' && Models\TicketCache::getValid($key, $updated) !== null) {
                $fromCache++;
                continue;
            }
            if ($budget <= 0) {
                $log("Budget IA atteint : {$done} ticket(s) analysé(s), {$fromCache} en cache. Suite au prochain passage.");
                exit(0);
            }
            $budget--;

            $comments = $jira->getIssueComments($key);
            $ai = $openRouter->getTicketSummaryAndAction(
                $key,
                (string) ($issue['fields']['summary'] ?? ''),
                strip_tags((string) ($issue['fields']['description'] ?? '')),
                $comments
            );

            if ($ai['summary'] !== '' || $ai['action'] !== '') {
                $latest = $comments ? trim((string) (end($comments)['body'] ?? '')) : '';
                Models\TicketCache::setCache(
                    $key,
                    $ai['summary'],
                    $ai['action'],
                    mb_strimwidth($latest, 0, 500, '…'),
                    $updated
                );
                $done++;
            }
        }
        $log("Sprint " . ($sprint['name'] ?? '?') . " traité.");
    }

    $log("Terminé : {$done} ticket(s) enrichi(s), {$fromCache} servi(s) depuis le cache.");
} catch (Throwable $e) {
    $log('ERREUR : ' . $e->getMessage());
    exit(1);
}
exit(0);
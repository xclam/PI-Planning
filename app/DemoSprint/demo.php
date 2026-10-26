<?php

declare(strict_types=1);

/**
 * Génère le PowerPoint de démo de sprint à partir de Jira.
 *   JIRA_TOKEN=... php demo.php [--sprint=ID] [--no-confluence]
 */

require __DIR__ . '/lib/PhpPresentation/Autoloader.php';
require __DIR__ . '/lib/Common/Autoloader.php';
\PhpOffice\PhpPresentation\Autoloader::register();
\PhpOffice\Common\Autoloader::register();

spl_autoload_register(static function (string $class): void {
    $prefix = 'DemoSprint\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use Core\ConfluenceClient;
use Core\HttpClient;
use Core\JiraClient;
use DemoSprint\SlideRenderer;
use DemoSprint\TicketFactory;
use DemoSprint\SprintReader;
use DemoSprint\UserDirectory;
use PhpOffice\PhpPresentation\IOFactory;

$config = require __DIR__ . '/config.php';
$opts   = getopt('', ['sprint::', 'no-confluence']) ?: [];

if ($config['jira']['token'] === '') {
    exit("JIRA_TOKEN manquant (variable d'environnement).\n");
}

try {
    $jira = new JiraClient(
        new HttpClient($config['jira']['token'], $config['jira']['timeout'], $config['jira']['ca_bundle']),
        $config['jira']['base_url']
    );

    $sprint = isset($opts['sprint'])
        ? $jira->getSprint((int) $opts['sprint'])
        : $jira->getActiveSprint($config['jira']['board_id']);

    $users   = new UserDirectory($jira, $config['roles'], $config['user_overrides']);
    $report  = (new SprintReader($jira, new TicketFactory($config), $users, $config))->read($sprint);
    $tickets = $report->tickets;
    if ($tickets === []) {
        exit("Aucun ticket à présenter dans le sprint « {$sprint['name']} » (voir règles dev/statuts) : pas de fichier généré.\n");
    }

    $confluence = null;
    if ($config['confluence']['enabled'] && !isset($opts['no-confluence'])) {
        $confluence = new ConfluenceClient(
            new HttpClient($config['confluence']['token'], $config['jira']['timeout'], $config['jira']['ca_bundle']),
            $config['confluence']['base_url']
        );
    }
    $catalog  = SiCatalog::build($config, $confluence);
    $renderer = new SlideRenderer($config['theme'], $catalog, $config['max_objective']);
    $ppt      = $renderer->render($report);

    if (!is_dir($config['output_dir']) && !mkdir($config['output_dir'], 0775, true) && !is_dir($config['output_dir'])) {
        exit("Impossible de créer {$config['output_dir']}\n");
    }
    $name = preg_replace('/[^\w\-]+/u', '_', $sprint['name']);
    $path = sprintf('%s/Demo_%s_%s.pptx', $config['output_dir'], $name, date('Ymd_His'));
    IOFactory::createWriter($ppt, 'PowerPoint2007')->save($path);
} catch (Throwable $e) {
    exit("Erreur : " . $e->getMessage() . "\n");
}

echo (count($tickets) + 1) . " slide(s) (1 récapitulative + " . count($tickets) . " ticket(s)) → $path\n";
foreach ($tickets as $t) {
    if ($missing = $t->missingFields()) {
        echo "  ⚠ {$t->key} ({$t->assignee}) : " . implode(', ', $missing) . " non renseigné(s)\n";
    }
    if ($t->si === null) {
        echo "  ⚠ {$t->key} : SI non reconnu dans le titre (format attendu « [B4ALL] [SI] [XXX] … »)\n";
    } elseif ($catalog->describe($t->si) === null) {
        echo "  ⚠ {$t->key} : SI « {$t->si} » absent du catalogue (ce premier tag est-il bien un SI ?)\n";
    }
}

foreach ($report->excluded as $e) {
    echo "  ✗ {$e['key']} exclu (chez {$e['holder']}) : {$e['reason']}\n";
}
foreach ($tickets as $t) {
    if ($t->assigneeInferred) {
        echo "  ↪ {$t->key} attribué à {$t->assignee} d'après l'historique\n";
    }
}
printSprintChanges('Ajoutés en cours de sprint', $report->added);
printSprintChanges('Sortis du sprint', $report->removed);

echo "\nPersonnes rencontrées (vérifie les rôles dans config.php) :\n";
foreach ($users->seen() as $p) {
    echo sprintf("  %-12s %-28s %s\n", $p->login, $p->name, $p->role->value);
}

function printSprintChanges(string $label, ?array $entries): void
{
    if ($entries === null) {
        echo "\n$label : indéterminable (sprint non démarré ou sprint report Jira indisponible)\n";
        return;
    }
    echo "\n$label : " . count($entries) . "\n";
    foreach ($entries as $e) {
        echo sprintf("  %-10s %-20s %-12s %s — %s\n", $e['key'], $e['type'], $e['status'], mb_strimwidth($e['title'], 0, 80, '…'), $e['who']);
    }
}
<?php
declare(strict_types=1);

namespace Services;

use Core\Factory;
use DemoSprint\CheckedSprintReader;
use DemoSprint\SlideRenderer;
use DemoSprint\TicketFactory;
use DemoSprint\UserDirectory;
use PhpOffice\PhpPresentation\IOFactory;
use RuntimeException;
use Throwable;

/**
 * Génération des slides de démo (PowerPoint) depuis les tickets cochés.
 * S'appuie sur les clients de app/Core (aucun client HTTP propre).
 */
final class DemoSlidesService
{
    /** Dossier de sortie : <racine du projet>/out (inaccessible du web : tout est réécrit vers public/). */
    public static function outputDir(): string
    {
        return dirname(__DIR__, 2) . '/out';
    }

    /**
     * Config DemoSprint (rôles, libellés, thème, tickets…) depuis
     * app/DemoSprint/config.php, avec les identifiants injectés depuis la config de l'app.
     */
    public static function demoConfig(): array
    {
        $file   = dirname(__DIR__) . '/DemoSprint/config.php';
        $config = is_file($file) ? (array) require $file : [];

        $config['jira'] ??= [];
        $config['jira']['base_url'] = (string) config('jira.base_url', '');

        $config['confluence'] ??= [];
        $config['confluence']['base_url'] = (string) config('confluence.base_url', '');

        $config['output_dir'] = self::outputDir();
        return $config;
    }

    /** Autoloaders PhpPresentation chargés une seule fois, seulement quand on génère. */
    private static function registerPhpPresentation(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $lib = dirname(__DIR__) . '/DemoSprint/lib';
        require $lib . '/PhpPresentation/Autoloader.php';
        require $lib . '/Common/Autoloader.php';
        \PhpOffice\PhpPresentation\Autoloader::register();
        \PhpOffice\Common\Autoloader::register();
        $done = true;
    }

    /**
     * @param array    $sprint        ['id' => …, 'name' => …]
     * @param array    $issues        données complètes des tickets cochés (fournies par l'app)
     * @param string[] $selectedKeys  clés cochées
     * @return string chemin absolu du .pptx généré
     */
    public function generate(array $sprint, array $issues, array $selectedKeys): string
    {
        self::registerPhpPresentation();
        $config = self::demoConfig();

        $users  = new UserDirectory(Factory::jira(), $config['roles'] ?? [], $config['user_overrides'] ?? []);
        $reader = new CheckedSprintReader(new TicketFactory($config), $users, $config);
        $report = $reader->read($sprint, $issues, $selectedKeys);

        if ($report->tickets === []) {
            $reasons = implode(' ; ', array_map(
                static fn (array $e): string => $e['key'] . ' → ' . $e['reason'],
                $report->excluded
            ));
            throw new RuntimeException(
                'Aucun ticket cochée exploitable pour les slides.' . ($reasons !== '' ? ' (' . $reasons . ')' : '')
            );
        }

        $renderer = new SlideRenderer($config['theme'] ?? [], $config['max_objective'] ?? null);
        $ppt      = $renderer->render($report);

        $dir = (string) $config['output_dir'];
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Impossible de créer le dossier de sortie {$dir}");
        }

        $name = preg_replace('/[^\w\-]+/u', '_', (string) ($sprint['name'] ?? 'Sprint'));
        $path = sprintf('%s/Demo_%s_%s.pptx', $dir, $name, date('Ymd_His'));
        IOFactory::createWriter($ppt, 'PowerPoint2007')->save($path);

        return $path;
    }
}
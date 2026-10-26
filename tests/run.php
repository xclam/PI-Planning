<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Core\ConfluenceClient;
use Services\DescriptionParser;
use Services\UserStoryService;

// ---------------------------------------------------------------- DescriptionParser
test('DescriptionParser — description nulle', static function (): void {
    $f = DescriptionParser::parse(null);
    eq('N/A', $f['Statut cadrage'], 'défaut Statut cadrage');
    eq('Kafka', $f['Type de flux'], 'défaut Type de flux');
    eq('json', $f['Format'], 'défaut Format');
});

test('DescriptionParser — table feature complète', static function (): void {
    $desc = "|*Statut cadrage*|OK|\n"
        . "|*SI Source*|GCP|\n"
        . "|*Nom du flux*|https://goconfluence/pageId=123456|\n"
        . "|*Type de flux*|topic.kafka.stream|\n"
        . "|*Précisions*|ligne 1\nligne 2 sur plusieurs lignes|\n"
        . "|*Planning*|Sprint 12|";
    $f = DescriptionParser::parse($desc);

    eq('OK', $f['Statut cadrage']);
    eq('GCP', $f['SI Source']);
    eq('Kafka', $f['Type de flux'], 'stream → Kafka');
    eq('topic.kafka.stream', $f['Topic'], 'Topic extrait du Type de flux');
    eq("ligne 1\nligne 2 sur plusieurs lignes", $f['Précisions'], 'valeur multiligne');
    eq('Sprint 12', $f['Planning']);
});

test('DescriptionParser — flux fichier (chorée)', static function (): void {
    $f = DescriptionParser::parse("|*Type de flux*|CHOREE-DIRECT|");
    eq('File', $f['Type de flux']);
    eq('CHOREE-DIRECT', $f['Chorée']);
    eq('', $f['Topic'], 'pas de Topic pour un flux fichier');
});

test('DescriptionParser — SEI oui / détection de format', static function (): void {
    $f = DescriptionParser::parse("|*DCI*|x| SEI : oui, format proto");
    eq('oui', $f['SEI'], 'SEI : oui détecté');
    eq('proto', $f['Format'], 'proto détecté');

    $f = DescriptionParser::parse("|*DCI*|x|\nFormat csv attendu");
    eq('csv', $f['Format']);
});

// ---------------------------------------------------------------- helpers
test('status_class — classification neutre', static function (): void {
    eq('done', status_class(['name' => 'Terminé', 'statusCategory' => ['key' => 'done']]));
    eq('done', status_class(['name' => 'Done']));
    eq('test', status_class(['name' => 'À tester', 'statusCategory' => ['key' => 'indeterminate']]));
    eq('test', status_class(['name' => 'En recette']));
    eq('progress', status_class(['name' => 'En cours', 'statusCategory' => ['key' => 'indeterminate']]));
    eq('todo', status_class(['name' => 'Backlog', 'statusCategory' => ['key' => 'todo']]));
});

test('url() — construction et requête', static function (): void {
    eq('/piplanning/public/', url());
    eq('/piplanning/public/pi/show?id=5', url('pi/show', ['id' => 5]));
});

test('csrf — jeton stable et vérification', static function (): void {
    $token = csrf_token();
    eq($token, csrf_token(), 'jeton stable en session');
    ok(csrf_valid() === false, 'sans _csrf POST, invalide');

    $_POST['_csrf'] = $token;
    ok(csrf_valid(), 'jeton valide accepté');

    $_POST['_csrf'] = $token . 'x';
    ok(csrf_valid() === false, 'jeton altéré rejeté');
    unset($_POST['_csrf'], $_SESSION['_csrf']);
});

test('sprint_options — sélection et échappement', static function (): void {
    $html = sprint_options([
        ['id' => 7, 'name' => 'Sprint <n°7>', 'state' => 'active'],
    ], 7, 'Sprint <n°7>');
    ok(str_contains($html, 'value="7|Sprint &lt;n°7&gt;"'), 'valeur échappée');
    ok(str_contains($html, 'selected'), 'sprint sélectionné');

    // Sprint enregistré absent de la liste (clôturé) → option "hors liste"
    $html = sprint_options([], 9, 'Ancien sprint');
    ok(str_contains($html, 'hors liste'), 'option hors liste générée');
});

test('ConfluenceClient::extractPageIdFromUrl', static function (): void {
    eq('123456', ConfluenceClient::extractPageIdFromUrl('https://x/pages/123456'));
    eq('987', ConfluenceClient::extractPageIdFromUrl('https://x/pageId=987&t=1'));
    eq(null, ConfluenceClient::extractPageIdFromUrl('https://x/sans-id'));
});

// ---------------------------------------------------------------- UserStoryService (méthode privée via réflexion)
test('UserStoryService::targets — SEI oui / non', static function (): void {
    $m = new ReflectionMethod(UserStoryService::class, 'targets');
    $m->setAccessible(true);
    $svc = new UserStoryService(
        new Core\JiraClient(new Core\HttpClient(''), 'https://jira.local'),
        null,
        'B4A'
    );

    [$path, $hive] = $m->invoke($svc, [
        'SI Source' => 'GESPRO',
        'SEI'       => 'non',
    ]);
    ok(str_ends_with($path, '/GESPRO/FRA/{om}'), 'PATH sans SEI');
    eq('GESPRO_{om}_fra', $hive, 'HIVE sans SEI');

    [$path, $hive] = $m->invoke($svc, [
        'SI Source' => 'GESPRO',
        'SEI'       => 'oui',
    ]);
    ok(str_contains($path, '/SEI/{om}'), 'PATH avec SEI');
    eq('GESPRO_{om}_fra ET GESPRO_{om}_sei', $hive, 'HIVE avec SEI');
});

// ---------------------------------------------------------------- Base de données (ignoré si non configurée)
test('Base + Model + caches (ignoré sans config/config.php)', static function (): void {
    if (!is_file(__DIR__ . '/../config/config.php')) {
        echo '  (ignoré : config/config.php absent)', PHP_EOL;
        return;
    }

    $db = null;
    try { $db = Core\Database::getConnection(); }
    catch (Throwable) {
        echo '  (ignoré : BDD injoignable)', PHP_EOL;
        return;
    }

    // Table jetable pour le CRUD
    $db->exec('CREATE TABLE IF NOT EXISTS test_widgets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, label VARCHAR(50))');

    $class = new class extends Core\Model {
        protected static ?string $table = 'test_widgets';
    };
    $w = $class::create(['label' => 'a']);
    eq(1, $w->id, 'insert → id');
    $w->label = 'b';
    ok($w->save(), 'update');
    eq('b', $class::find($w->id)->label, 'relecture après update');
    ok($w->save(), 'save sans changement (no-op)');
    ok($w->delete(), 'delete');

    // CreatedUs / TicketCache — nécessitent le schéma (database/schema.sql)
    try {
        Models\CreatedUs::record(999999, 'feature', 'TEST-1', 'US-999');
        eq('US-999', Models\CreatedUs::mapForPi(999999)['TEST-1'] ?? '', 'mapForPi');
        eq(1, $db->exec('DELETE FROM created_us WHERE pi_id = 999999'), 'nettoyage created_us');
    } catch (Throwable $e) {
        echo '  (schéma created_us absent ? ', $e->getMessage(), ')', PHP_EOL;
    }

    try {
        Models\TicketCache::setCache('TEST-1', 'résumé', 'action', 'commentaire', '2026-10-26T10:00:00.000+0200');
        $c = Models\TicketCache::getValid('TEST-1', '2026-10-26T10:00:00.000+0200');
        ok($c !== null && $c['summary'] === 'résumé', 'cache valide');
        ok(Models\TicketCache::getValid('TEST-1', '2026-10-26T11:00:00.000+0200') === null, 'cache invalidé par updated');
        $db->exec("DELETE FROM ticket_ai_cache WHERE ticket_key = 'TEST-1'");
    } catch (Throwable $e) {
        echo '  (schéma ticket_ai_cache absent ? ', $e->getMessage(), ')', PHP_EOL;
    }
});

results();
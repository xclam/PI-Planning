<?php
declare(strict_types=1);

namespace Controllers;

use Core\Factory;
use Core\View;
use DateTimeImmutable;
use Models\CreatedUs;
use Models\Pi;
use Services\UserStoryService;

final class PiController
{
    public function index(): void
    {
        [$activeSprint, $sprintStats, $errorJira] = $this->loadActiveSprint();

        View::render('pi/index', [
            'title'        => 'Program Increments',
            'pis'          => Pi::all(),
            'activeSprint' => $activeSprint,
            'sprintStats'  => $sprintStats,
            'errorJira'    => $errorJira,
        ]);
    }

    public function create(): void
    {
        $lastPi   = Pi::last();
        $nextName = 'PI DI1';
        if ($lastPi && preg_match('/PI DI(\d+)/i', (string) $lastPi->name, $m)) {
            $nextName = 'PI DI' . ((int) $m[1] + 1);
        }

        $sprints   = [];
        $errorJira = null;
        try {
            $sprints = Factory::jira()->getBoardSprints((int) config('jira.art_board_id'));
        } catch (\Throwable $e) {
            $errorJira = 'Impossible de récupérer les sprints Jira : ' . $e->getMessage();
        }

        View::render('pi/create', [
            'title'     => 'Nouveau Program Increment',
            'nextName'  => $nextName,
            'sprints'   => $sprints,
            'errorJira' => $errorJira,
        ]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->readInput($_POST);

        if (!$errors && Pi::findBy(['name' => $data['name']])) {
            $errors[] = 'Un PI portant ce nom existe déjà.';
        }
        if ($errors) {
            $this->failWith($errors, 'pi/create');
        }

        $pi = Pi::create($data);
        flash('success', 'PI « ' . $data['name'] . ' » créé.');
        redirect('pi/show', ['id' => $pi->id]);
    }

    public function show(): void
    {
        $pi = $this->findPiOr404((int) ($_GET['id'] ?? 0));

        $sprints = $features = $enablers = [];
        $errorJira = null;

        try {
            $jira       = Factory::jira();
            $projectKey = (string) config('jira.project_key', 'B4A');
            $sprints    = $jira->getBoardSprints((int) config('jira.art_board_id'));

            if ($pi->feature_sprint_id) {
                $features = UserStoryService::filterByPrefix(
                    $jira->getSprintIssues($projectKey, (int) $pi->feature_sprint_id),
                    UserStoryService::FEATURE_PREFIX
                );
            }
            if ($pi->enabler_sprint_id) {
                $enablers = UserStoryService::filterByPrefix(
                    $jira->getSprintIssues($projectKey, (int) $pi->enabler_sprint_id),
                    UserStoryService::ENABLER_PREFIX
                );
            }
        } catch (\Throwable $e) {
            $errorJira = 'Impossible de récupérer les données Jira : ' . $e->getMessage();
        }

        View::render('pi/show', [
            'title'     => 'PI : ' . $pi->name,
            'pi'        => $pi,
            'sprints'   => $sprints,
            'features'  => $features,
            'enablers'  => $enablers,
            'createdUs' => CreatedUs::mapForPi((int) $pi->id),
            'errorJira' => $errorJira,
        ]);
    }

    public function update(): void
    {
        $pi = $this->findPiOr404((int) ($_POST['id'] ?? 0));
        [$data, $errors] = $this->readInput($_POST);

        if (!$errors) {
            $duplicate = Pi::findBy(['name' => $data['name']]);
            if ($duplicate && (int) $duplicate->id !== (int) $pi->id) {
                $errors[] = 'Un autre PI porte déjà ce nom.';
            }
        }
        if ($errors) {
            $this->failWith($errors, 'pi/show', ['id' => $pi->id]);
        }

        $pi->fill($data)->save();
        flash('success', 'PI mis à jour.');
        redirect('pi/show', ['id' => $pi->id]);
    }

    public function createUs(): void
    {
        $pi = $this->findPiOr404((int) ($_POST['id'] ?? 0));

        $features = self::sanitizeKeys($_POST['features'] ?? []);
        $enablers = self::sanitizeKeys($_POST['enablers'] ?? []);

        if (!$features && !$enablers) {
            flash('warning', 'Aucun ticket sélectionné.');
            redirect('pi/show', ['id' => $pi->id]);
        }

        set_time_limit(300); // N créations + liens + Confluence : peut dépasser 30 s

        try {
            $service = new UserStoryService(
                Factory::jira(),
                Factory::confluence(),
                (string) config('jira.project_key', 'B4A')
            );
            $result = $service->createForPi($pi, $features, $enablers);
        } catch (\Throwable $e) {
            flash('error', 'Erreur Jira : ' . $e->getMessage());
            redirect('pi/show', ['id' => $pi->id]);
        }

        if ($result['created']) {
            flash('success', count($result['created']) . ' US créée(s) : ' . implode(', ', $result['created']) . '.');
        }
        if ($result['skipped']) {
            flash('info', count($result['skipped']) . ' ticket(s) déjà traité(s), ignoré(s) : ' . implode(', ', array_keys($result['skipped'])) . '.');
        }
        foreach ($result['warnings'] as $warning) {
            flash('warning', $warning);
        }
        foreach ($result['errors'] as $key => $message) {
            flash('error', "{$key} : {$message}");
        }

        redirect('pi/show', ['id' => $pi->id]);
    }

    // ------------------------------------------------------------------ privé

    private function findPiOr404(int $id): Pi
    {
        $pi = $id > 0 ? Pi::find($id) : null;
        if (!$pi) {
            abort(404, 'Program Increment introuvable.');
        }
        return $pi;
    }

    /** @return array{0: ?array, 1: array, 2: ?string} */
    private function loadActiveSprint(): array
    {
        try {
            $data = remember('active_sprint', 120, function (): array {
                $jira    = Factory::jira();
                $sprints = $jira->getBoardSprints((int) config('jira.team_board_id', 4), 'active');
                $active  = $sprints[0] ?? null; // plus de repli sur un sprint futur présenté comme "en cours"

                if (!$active) {
                    return ['sprint' => null, 'stats' => []];
                }
                $issues = $jira->getSprintIssues((string) config('jira.project_key', 'B4A'), (int) $active['id']);
                return ['sprint' => $active, 'stats' => self::computeStats($issues)];
            });
            return [$data['sprint'], $data['stats'], null];
        } catch (\Throwable $e) {
            error_log('Sprint actif : ' . $e->getMessage());
            return [null, [], 'Statistiques du sprint indisponibles : ' . $e->getMessage()];
        }
    }

    private static function computeStats(array $issues): array
    {
        $stats = ['total' => count($issues), 'done' => 0, 'in_progress' => 0, 'todo' => 0, 'anomalies' => 0];

        foreach ($issues as $issue) {
            $fields = $issue['fields'] ?? [];
            switch (status_class($fields['status'] ?? [])) {
                case 'done':
                    $stats['done']++;
                    break;
                case 'progress':
                case 'test':
                    $stats['in_progress']++;
                    break;
                default:
                    $stats['todo']++;
            }

            $type = mb_strtolower((string) ($fields['issuetype']['name'] ?? ''));
            if (str_contains($type, 'anomalie') || str_contains($type, 'bug')) {
                $stats['anomalies']++;
            }
        }
        return $stats;
    }

    /** @return array{0: array, 1: string[]} [données, erreurs] */
    private function readInput(array $post): array
    {
        $name  = trim((string) ($post['name'] ?? ''));
        $start = trim((string) ($post['start_date'] ?? ''));
        $end   = trim((string) ($post['end_date'] ?? ''));

        [$featureId, $featureName] = self::parseSprintChoice($post['feature_sprint'] ?? '');
        [$enablerId, $enablerName] = self::parseSprintChoice($post['enabler_sprint'] ?? '');

        $errors = [];
        if ($name === '') {
            $errors[] = 'Le nom du PI est obligatoire.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Le nom du PI ne doit pas dépasser 100 caractères.';
        }

        $startDate = self::parseDate($start);
        $endDate   = self::parseDate($end);
        if (!$startDate) {
            $errors[] = 'Date de début invalide.';
        }
        if (!$endDate) {
            $errors[] = 'Date de fin invalide.';
        }
        if ($startDate && $endDate && $endDate < $startDate) {
            $errors[] = 'La date de fin doit être postérieure ou égale à la date de début.';
        }

        return [[
            'name'                => $name,
            'start_date'          => $start,
            'end_date'            => $end,
            'feature_sprint_id'   => $featureId,
            'feature_sprint_name' => $featureName,
            'enabler_sprint_id'   => $enablerId,
            'enabler_sprint_name' => $enablerName,
        ], $errors];
    }

    private function failWith(array $errors, string $backPath, array $query = []): never
    {
        with_old($_POST);
        foreach ($errors as $error) {
            flash('error', $error);
        }
        redirect($backPath, $query);
    }

    /** @return array{0: ?int, 1: ?string} */
    private static function parseSprintChoice(mixed $raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [null, null];
        }
        [$id, $name] = array_pad(explode('|', $raw, 2), 2, null);
        if (!ctype_digit((string) $id)) {
            return [null, null];
        }
        return [(int) $id, $name !== null ? trim($name) : null];
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** @return string[] */
    private static function sanitizeKeys(mixed $input): array
    {
        $keys = [];
        foreach ((array) $input as $key) {
            if (is_string($key) && preg_match('/^[A-Z][A-Z0-9_]*-\d+$/', $key)) {
                $keys[] = $key;
            }
        }
        return array_values(array_unique($keys));
    }
}
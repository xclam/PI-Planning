<?php
declare(strict_types=1);

namespace Controllers;

use Core\Factory;
use Core\View;
use Models\TicketCache;

final class SprintController
{
    public function show(): void
    {
        $sprintId = (int) ($_GET['id'] ?? 0);
        if ($sprintId <= 0) {
            abort(400, 'Identifiant de sprint manquant.');
        }

        $sprintName    = trim((string) ($_GET['name'] ?? '')) ?: 'Sprint';
        $currentUser   = trim((string) config('jira.username', ''));
        $issues        = [];
        $myTickets     = [];
        $statusCounts  = [];
        $errorJira     = null;

        try {
            $jira       = Factory::jira();
            $openRouter = Factory::openRouter();
            $projectKey = (string) config('jira.project_key', 'B4A');

            try {
                $sprintName = (string) ($jira->getSprint($sprintId)['name'] ?? $sprintName);
            } catch (\Throwable) {
                // on garde le nom passé en paramètre
            }

            $issues = $jira->getSprintIssues($projectKey, $sprintId);

            foreach ($issues as &$issue) {
                $fields = $issue['fields'] ?? [];
                $issue['_status_class'] = status_class($fields['status'] ?? []);
                $issue['_is_demo_type'] = self::isDemoType((string) ($fields['issuetype']['name'] ?? ''));
                $issue['_preselect']    = $issue['_is_demo_type'] && in_array($issue['_status_class'], ['test', 'done'], true);

                $statusName = (string) ($fields['status']['name'] ?? '?');
                $statusCounts[$statusName] = ($statusCounts[$statusName] ?? 0) + 1;
            }
            unset($issue);
            arsort($statusCounts);

            if ($currentUser !== '') {
                $myTickets = $this->buildMyTickets($issues, $currentUser, $jira, $openRouter);
            }
        } catch (\Throwable $e) {
            $errorJira = 'Erreur lors de la récupération des données du sprint : ' . $e->getMessage();
        }

        View::render('sprint/show', [
            'title'        => 'Sprint : ' . $sprintName,
            'sprintName'   => $sprintName,
            'sprintId'     => $sprintId,
            'issues'       => $issues,
            'myTickets'    => $myTickets,
            'statusCounts' => $statusCounts,
            'missingUser'  => $currentUser === '',
            'errorJira'    => $errorJira,
        ]);
    }

    private function buildMyTickets(array $issues, string $currentUser, \Core\JiraClient $jira, \Core\OpenRouter $openRouter): array
    {
        // Budget d'appels IA par chargement de page : évite les timeouts sur un gros sprint.
        $aiBudget = $openRouter->isConfigured() ? (int) config('openrouter.max_calls_per_request', 15) : 0;
        $mine     = [];

        foreach ($issues as $issue) {
            $assignee = $issue['fields']['assignee'] ?? [];
            $isMine   = false;
            foreach (['name', 'key', 'displayName'] as $field) {
                if (isset($assignee[$field]) && strcasecmp((string) $assignee[$field], $currentUser) === 0) {
                    $isMine = true;
                    break;
                }
            }
            if (!$isMine) {
                continue;
            }

            $key            = (string) $issue['key'];
            $updatedRaw     = (string) ($issue['fields']['updated'] ?? '');
            $updatedTs      = $updatedRaw !== '' ? (int) strtotime($updatedRaw) : 0;
            $issue['is_recent_24h'] = $updatedTs >= (time() - 86400);

            $cached = $updatedRaw !== '' ? TicketCache::getValid($key, $updatedRaw) : null;

            if ($cached !== null) {
                $issue['latest_comment'] = $cached['latest_comment'] !== '' ? $cached['latest_comment'] : 'Aucun commentaire';
                $issue['ai_summary']     = $cached['summary'];
                $issue['ai_action']      = $cached['action'];
            } else {
                $comments = [];
                try {
                    $comments = $jira->getIssueComments($key);
                    $latest   = $comments ? trim((string) (end($comments)['body'] ?? '')) : '';
                } catch (\Throwable) {
                    $latest = 'N/A';
                }
                $issue['latest_comment'] = $latest !== '' ? mb_strimwidth($latest, 0, 500, '…') : 'Aucun commentaire';
                $issue['ai_summary']     = '';
                $issue['ai_action']      = '';

                if ($aiBudget > 0) {
                    $aiBudget--;
                    $ai = $openRouter->getTicketSummaryAndAction(
                        $key,
                        (string) ($issue['fields']['summary'] ?? ''),
                        strip_tags((string) ($issue['fields']['description'] ?? '')),
                        $comments
                    );
                    $issue['ai_summary'] = $ai['summary'] ?? '';
                    $issue['ai_action']  = $ai['action'] ?? '';

                    if (($issue['ai_summary'] !== '' || $issue['ai_action'] !== '') && $updatedRaw !== '') {
                        TicketCache::setCache($key, $issue['ai_summary'], $issue['ai_action'], $issue['latest_comment'], $updatedRaw);
                    }
                }
            }

            $issue['_updated_ts'] = $updatedTs;
            $mine[] = $issue;
        }

        // Modifiés dans les dernières 24 h d'abord, puis du plus récent au plus ancien
        usort($mine, static fn(array $a, array $b): int =>
            ($b['is_recent_24h'] <=> $a['is_recent_24h']) ?: ($b['_updated_ts'] <=> $a['_updated_ts'])
        );

        return $mine;
    }

    /**
     * "us" doit être un mot entier : l'ancien str_contains($type, 'us') cochait aussi "Sous-tâche", "Business"…
     */
    private static function isDemoType(string $type): bool
    {
        return preg_match('/(?<!\pL)(us|user story|enabler|feature)(?!\pL)/u', mb_strtolower($type)) === 1;
    }
}
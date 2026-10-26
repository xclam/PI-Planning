<?php
declare(strict_types=1);

namespace Core;

use RuntimeException;

final class JiraClient
{
    private readonly string $base;
    private readonly string $api;
    private readonly string $agile;

    /** Mémo par requête : évite de recharger deux fois le même sprint. */
    private array $sprintIssuesMemo = [];

    public function __construct(private readonly HttpClient $http, string $baseUrl)
    {
        $this->base  = rtrim($baseUrl, '/');
        $this->api   = $this->base . '/rest/api/2/';
        $this->agile = $this->base . '/rest/agile/1.0/';
    }

    public function getBoardSprints(int $boardId, string $states = 'active,future'): array
    {
        $data = $this->http->get($this->agile . "board/{$boardId}/sprint", [
            'state'      => $states,
            'maxResults' => 100,
        ]);
        return $data['values'] ?? [];
    }

    public function getSprint(int $sprintId): array
    {
        return $this->http->get($this->agile . "sprint/{$sprintId}");
    }

    public function getSprintIssues(string $project, int $sprintId, array $extraFields = []): array
    {
        $memoKey = $project . '|' . $sprintId . '|' . implode(',', $extraFields);
        if (isset($this->sprintIssuesMemo[$memoKey])) {
            return $this->sprintIssuesMemo[$memoKey];
        }

        $jql = sprintf('project = "%s" AND sprint = %d ORDER BY key ASC', addcslashes($project, '"\\'), $sprintId);
        // 'updated' manquait : "modifié < 24h" ne pouvait jamais s'activer.
        // Le changelog (lourd, jamais utilisé) n'est plus demandé.
        $fields = array_values(array_unique(array_merge(
            ['summary', 'description', 'status', 'issuetype', 'assignee', 'created', 'updated'],
            $extraFields
        )));

        return $this->sprintIssuesMemo[$memoKey] = $this->searchAll($jql, $fields);
    }

    public function getIssuesByKeys(string $project, array $keys, array $types = [], array $extraFields = []): array
    {
        $keys = array_values(array_filter($keys, static fn(string $k): bool => (bool) preg_match('/^[A-Z][A-Z0-9_]*-\d+$/', $k)));
        $out  = [];
        foreach (array_chunk($keys, 100) as $chunk) {
            $jql = sprintf('project = "%s" AND key in (%s)', addcslashes($project, '"\\'), implode(',', $chunk));
            if ($types !== []) {
                $jql .= ' AND issuetype in (' . implode(',', array_map(static fn(string $t): string => '"' . addcslashes($t, '"\\') . '"', $types)) . ')';
            }
            $out = array_merge($out, $this->searchAll(
                $jql . ' ORDER BY key ASC',
                array_merge(['summary', 'status', 'issuetype', 'assignee'], $extraFields)
            ));
        }
        return $out;
    }

    public function getUserByKey(string $key): ?array
    {
        try {
            return $this->http->get($this->api . 'user', ['key' => $key]);
        } catch (RuntimeException) {
            return null;
        }
    }

    public function getSprintReport(int $boardId, int $sprintId): ?array
    {
        try {
            $res = $this->http->get($this->base . '/rest/greenhopper/1.0/rapid/charts/sprintreport', [
                'rapidViewId' => $boardId,
                'sprintId'    => $sprintId,
            ]);
            return $res['contents'] ?? null;
        } catch (RuntimeException) {
            return null;
        }
    }

    public function createIssue(array $fields): array
    {
        return $this->http->post($this->api . 'issue', ['fields' => $fields]);
    }

    /**
     * Lie deux tickets. Retourne false (et loggue) si aucun type de lien n'a fonctionné,
     * sans casser la boucle appelante.
     */
    public function linkIssues(string $childKey, string $parentKey, string $linkTypeName = 'Relates'): bool
    {
        $names = array_values(array_unique(array_merge(
            [$linkTypeName],
            (array) config('jira.link_types', ['Relates', 'Relatif', 'Concerne', 'Lien'])
        )));

        $last = null;
        foreach ($names as $name) {
            try {
                $this->http->post($this->api . 'issueLink', [
                    'type'         => ['name' => $name],
                    'inwardIssue'  => ['key' => $parentKey],
                    'outwardIssue' => ['key' => $childKey],
                ]);
                return true;
            } catch (\Throwable $e) {
                $last = $e;
            }
        }

        error_log("Jira : lien {$childKey} ↔ {$parentKey} impossible : " . ($last?->getMessage() ?? '?'));
        return false;
    }

    public function getIssueComments(string $issueKey): array
    {
        $response = $this->http->get($this->api . 'issue/' . rawurlencode($issueKey) . '/comment', ['orderBy' => 'created']);
        return $response['comments'] ?? [];
    }

    private function searchAll(string $jql, array $fields): array
    {
        $issues  = [];
        $startAt = 0;
        do {
            $page    = $this->http->post($this->api . 'search', [
                'jql'        => $jql,
                'fields'     => $fields,
                'startAt'    => $startAt,
                'maxResults' => 100,
            ]);
            $batch   = $page['issues'] ?? [];
            $issues  = array_merge($issues, $batch);
            $startAt += count($batch);
            $total   = (int) ($page['total'] ?? 0);
        } while ($batch !== [] && $startAt < $total);

        return $issues;
    }
}
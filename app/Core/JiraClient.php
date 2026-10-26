<?php
declare(strict_types=1);

namespace Core;

use RuntimeException;

final class JiraClient
{
    private readonly string $base;
    private readonly string $api;
    private readonly string $agile;

    public function __construct(private readonly HttpClient $http, string $baseUrl)
    {
        $this->base  = rtrim($baseUrl, '/');
        $this->api   = $this->base . '/rest/api/2/';
        $this->agile = $this->base . '/rest/agile/1.0/';
    }

    public function getBoardSprints(int $boardId): array
    {
        $data = $this->http->get($this->agile . "board/{$boardId}/sprint", [
            'state' => 'active,future', // Uniquement les sprints actifs et futurs
            'maxResults' => 100
        ]);
        return $data['values'] ?? [];
    }

    public function getSprint(int $sprintId): array
    {
        return $this->http->get($this->agile . "sprint/{$sprintId}");
    }

    public function getSprintIssues(string $project, int $sprintId, array $extraFields = []): array
    {
        $jql    = sprintf('project = "%s" AND sprint = %d ORDER BY key ASC', $project, $sprintId);
        $fields = array_merge(['summary', 'description', 'status', 'issuetype', 'assignee', 'created'], $extraFields);
        return $this->searchAll($jql, $fields, ['changelog']);
    }

    public function getIssuesByKeys(string $project, array $keys, array $types = []): array
    {
        $keys = array_values(array_filter($keys, static fn(string $k): bool => (bool) preg_match('/^[A-Z][A-Z0-9_]*-\d+$/', $k)));
        $out  = [];
        foreach (array_chunk($keys, 100) as $chunk) {
            $jql = sprintf('project = "%s" AND key in (%s)', $project, implode(',', $chunk));
            if ($types !== []) {
                $jql .= ' AND issuetype in (' . implode(',', array_map(static fn(string $t): string => '"' . addcslashes($t, '"\\') . '"', $types)) . ')';
            }
            $out = array_merge($out, $this->searchAll($jql . ' ORDER BY key ASC', ['summary', 'status', 'issuetype', 'assignee']));
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

    public function getSprintReport(int $boardId, int$sprintId): ?array
    {
        try {
            $res = $this->http->get($this->base . '/rest/greenhopper/1.0/rapid/charts/sprintreport', [
                'rapidViewId' => $boardId, 'sprintId' => $sprintId,
            ]);
            return $res['contents'] ?? null;
        } catch (RuntimeException) {
            return null;
        }
    }

    private function searchAll(string $jql, array $fields, array $expand = []): array
    {
        $issues  = [];
        $startAt = 0;
        do {
            $payload = ['jql' => $jql, 'fields' => $fields, 'startAt' => $startAt, 'maxResults' => 100];
            if ($expand !== []) {
                $payload['expand'] = $expand;
            }
            $page    = $this->http->post($this->api . 'search', $payload);
            $batch   = $page['issues'] ?? [];
            $issues  = array_merge($issues, $batch);
            $startAt += count($batch);
            $total   = (int) ($page['total'] ?? 0);
        } while ($batch !== [] && $startAt < $total);

        return $issues;
    }

    public function createIssue(array $fields): array
    {
        return $this->http->post($this->api . 'issue', [
            'fields' => $fields
        ]);
    }

    public function linkIssues(string $childKey, string $parentKey, string $linkTypeName = 'Relatif'): void
    {
        // Essai avec le nom fourni, ou bascule sur les alternatives courantes en français
        $possibleNames = [$linkTypeName, 'Relatif', 'Concerne', 'Lien'];
        
        $lastException = null;
        foreach ($possibleNames as $name) {
            try {
                $this->http->post($this->api . 'issueLink', [
                    'type' => ['name' => $name],
                    'inwardIssue' => ['key' => $parentKey],
                    'outwardIssue' => ['key' => $childKey],
                ]);
                return; // Si ça passe, on sort
            } catch (\Exception $e) {
                $lastException = $e;
            }
        }
        
        // Si aucun nom n'a fonctionné, on log ou ignore pour ne pas casser la boucle de création
        // (Tu pourras ajuster le nom exact si tu le trouves dans l'administration Jira)
    }

    public function getIssueComments(string $issueKey): array {
        $response = $this->http->get($this->api . 'issue/' . $issueKey . '/comment');
        return $response['comments'] ?? [];
    }
}
<?php

declare(strict_types=1);

namespace DemoSprint;

use RuntimeException;

/** Lit le sprint, calcule les indicateurs et sélectionne les tickets à présenter. */
final class SprintReader
{
    public function __construct(
        private readonly JiraClient $jira,
        private readonly TicketFactory $factory,
        private readonly UserDirectory $users,
        private readonly array $config,
    ) {
    }

    public function read(array $sprint): SprintReport
    {
        $sprintId    = (int) $sprint['id'];
        $project     = $this->config['jira']['project'];
        $cfg         = $this->config['tickets'];
        $order       = array_flip($cfg['type_order']);
        $accepted    = array_merge($cfg['done_statuses'], $cfg['review_statuses']);
        $statsTypes  = $cfg['stats_types'];                       // null = tous les types
        $pointsField = $this->config['jira']['story_points_field'] ?? null;

        $issues = $this->jira->getSprintIssues($project, $sprintId, $pointsField ? [$pointsField] : []);
        $report = $this->jira->getSprintReport($this->config['jira']['board_id'], $sprintId);

        $inScope = static fn(array $issue): bool => $statsTypes === null
            || in_array(trim((string) ($issue['fields']['issuetype']['name'] ?? '')), $statsTypes, true);
        $scoped = array_values(array_filter($issues, $inScope));

        $addedKeys = $this->addedKeys($scoped, $sprint, $report);
        $added     = $addedKeys === null ? null : $this->entries($scoped, $addedKeys);
        $removed   = $this->removedEntries($project, $report, $statsTypes);

        $stats = [
            'total' => count($scoped), 'todo' => 0, 'in_progress' => 0, 'done' => 0,
            'added'   => $added === null ? null : count($added),
            'removed' => $removed === null ? null : count($removed),
            'points_total' => $pointsField ? 0.0 : null,
            'points_done'  => $pointsField ? 0.0 : null,
            'by_dev' => [],
        ];

        $tickets  = [];
        $excluded = [];

        foreach ($issues as $issue) {
            $f = $issue['fields'] ?? [];

            if ($inScope($issue)) {
                $category = (string) ($f['status']['statusCategory']['key'] ?? 'new');   // indépendant de la langue
                match ($category) {
                    'done'          => $stats['done']++,
                    'indeterminate' => $stats['in_progress']++,
                    default         => $stats['todo']++,
                };
                if ($pointsField) {
                    $points = (float) ($f[$pointsField] ?? 0);
                    $stats['points_total'] += $points;
                    if ($category === 'done') {
                        $stats['points_done'] += $points;
                    }
                }
            }

            $type   = trim((string) ($f['issuetype']['name'] ?? ''));
            $status = trim((string) ($f['status']['name'] ?? ''));
            if (!isset($order[$type]) || !in_array($status, $accepted, true)) {
                continue;
            }

            [$dev, $inferred] = $this->resolveDev($issue);
            if ($dev === null) {
                $holder     = $this->users->fromIssueUser($f['assignee'] ?? null);
                $excluded[] = [
                    'key'    => (string) $issue['key'],
                    'title'  => (string) ($f['summary'] ?? ''),
                    'holder' => $holder ? "{$holder->name} ({$holder->role->value})" : 'non assigné',
                    'reason' => "aucun dev dans l'historique d'affectation",
                ];
                continue;
            }
            $tickets[] = $this->factory->fromIssue($issue, $dev, $inferred);
        }

        usort($tickets, static fn(Ticket $a, Ticket $b): int =>
            ($order[$a->type] <=> $order[$b->type])
            ?: strcasecmp($a->assignee, $b->assignee)
            ?: strnatcasecmp($a->key, $b->key)
        );

        foreach ($tickets as $t) {
            $stats['by_dev'][$t->assignee]['role']  = $t->role;
            $stats['by_dev'][$t->assignee]['total'] = ($stats['by_dev'][$t->assignee]['total'] ?? 0) + 1;
            $stats['by_dev'][$t->assignee]['done']  = ($stats['by_dev'][$t->assignee]['done'] ?? 0) + ($t->isDone ? 1 : 0);
        }

        return new SprintReport($sprint, $tickets, $excluded, $stats, $added, $removed);
    }

    /**
     * Dev à créditer : l'assigné courant s'il est dev ; sinon le dernier dev (chronologiquement)
     * présent dans l'historique des affectations.
     * @return array{0:?Person,1:bool} [dev, déduit de l'historique ?]
     */
    private function resolveDev(array $issue): array
    {
        $current = $this->users->fromIssueUser($issue['fields']['assignee'] ?? null);
        if ($current !== null && $current->role->isDev()) {
            return [$current, false];
        }

        $last = null;
        foreach (Changelog::changes($issue, 'assignee') as $change) {
            $person = $this->users->fromChangelog($change['to'], $change['toString']);
            if ($person !== null && $person->role->isDev()) {
                $last = $person;
            }
        }
        return [$last, $last !== null];
    }

    /**
     * Clés des tickets ajoutés après le démarrage du sprint (parmi $issues, déjà filtrés par type).
     * Priorité à la définition de Jira (sprint report) ; repli sur le calcul via changelog.
     * @return array<string,true>|null null = indéterminable (sprint non démarré)
     */
    private function addedKeys(array $issues, array $sprint, ?array $report): ?array
    {
        $present = [];
        foreach ($issues as $issue) {
            $present[(string) $issue['key']] = true;
        }

        if ($report !== null && array_key_exists('issueKeysAddedDuringSprint', $report)) {
            return array_intersect_key(array_fill_keys(array_keys((array) $report['issueKeysAddedDuringSprint']), true), $present);
        }

        $start = Changelog::date($sprint['startDate'] ?? null);
        if ($start === null) {
            return null;
        }
        $threshold = $start->modify('+' . (int) $this->config['added_grace_minutes'] . ' minutes');
        $sprintId  = (string) $sprint['id'];

        $added = [];
        foreach ($issues as $issue) {
            $created = Changelog::date($issue['fields']['created'] ?? null);
            if ($created !== null && $created > $threshold) {
                $added[(string) $issue['key']] = true;   // créé directement dans le sprint
                continue;
            }
            foreach (Changelog::changes($issue, 'Sprint') as $c) {
                $joined = !in_array($sprintId, Changelog::ids($c['from']), true) && in_array($sprintId, Changelog::ids($c['to']), true);
                if ($joined && $c['created'] > $threshold) {
                    $added[(string) $issue['key']] = true;
                    break;
                }
            }
        }
        return $added;
    }

    /**
     * Tickets sortis du sprint : seule source = sprint report Jira (ils ne sont plus liés au sprint).
     * On ne retient que les clés, qualifiées ensuite par l'API officielle (type, statut, assigné).
     * @return array<int,array>|null null = indéterminable
     */
    private function removedEntries(string $project, ?array $report, ?array $types): ?array
    {
        if ($report === null) {
            return null;
        }
        $keys = array_map(static fn(array $i): string => (string) ($i['key'] ?? ''), $report['puntedIssues'] ?? []);
        try {
            return array_map($this->entry(...), $this->jira->getIssuesByKeys($project, $keys, $types ?? []));
        } catch (RuntimeException $e) {
            echo "ATTENTION : impossible de qualifier les tickets sortis du sprint (" . $e->getMessage() . ")\n";
            return null;
        }
    }

    /** @param array<string,true> $keys */
    private function entries(array $issues, array $keys): array
    {
        return array_map($this->entry(...), array_values(array_filter(
            $issues,
            static fn(array $i): bool => isset($keys[(string) $i['key']])
        )));
    }

    private function entry(array $issue): array
    {
        $f      = $issue['fields'] ?? [];
        $person = $this->users->fromIssueUser($f['assignee'] ?? null);
        return [
            'key'    => (string) $issue['key'],
            'title'  => (string) ($f['summary'] ?? ''),
            'type'   => trim((string) ($f['issuetype']['name'] ?? '')),
            'status' => trim((string) ($f['status']['name'] ?? '')),
            'who'    => $person ? "{$person->name} ({$person->role->value})" : 'non assigné',
        ];
    }
}
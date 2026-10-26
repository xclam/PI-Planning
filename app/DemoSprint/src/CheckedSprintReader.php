<?php
declare(strict_types=1);

namespace DemoSprint;

use Services\DescriptionParser;

/**
 * Construit le rapport de démo UNIQUEMENT à partir des tickets cochés dans la page.
 * Aucun appel de listing Jira : les issues sont fournies par l'application.
 */
final class CheckedSprintReader
{
    public function __construct(
        private readonly TicketFactory $factory,
        private readonly UserDirectory $users,
        private readonly array $config,
    ) {
    }

    /**
     * @param array    $issues       issues complètes (celles des clés cochées)
     * @param string[] $selectedKeys  clés cochées — la seule source de sélection
     */
    public function read(array $sprint, array $issues, array $selectedKeys): SprintReport
    {
        $selected = array_fill_keys($selectedKeys, true);
        $order    = $this->config['tickets']['type_order'] ?? [];

        $stats = [
            'total'         => count($selectedKeys),
            'todo'          => 0,
            'in_progress'   => 0,
            'done'          => 0,
            'added'         => null,
            'removed'       => null,
            'points_total'  => null,
            'points_done'   => null,
            'by_dev'        => [],
        ];

        $tickets  = [];
        $excluded = [];

        foreach ($issues as $issue) {
            $key = (string) $issue['key'];
            if (!isset($selected[$key])) {
                continue;
            }

            $f        = $issue['fields'] ?? [];
            $category = (string) ($f['status']['statusCategory']['key'] ?? 'new');
            match ($category) {
                'done'          => $stats['done']++,
                'indeterminate' => $stats['in_progress']++,
                default         => $stats['todo']++,
            };

            $dev = $this->users->fromIssueUser($f['assignee'] ?? null);
            if ($dev === null || !$dev->role->isDev()) {
                $holder     = $this->users->fromIssueUser($f['assignee'] ?? null);
                $excluded[] = [
                    'key'    => $key,
                    'title'  => (string) ($f['summary'] ?? ''),
                    'holder' => $holder !== null
                        ? $holder->name . ' (' . $holder->role->value . ')'
                        : 'non assigné',
                    'reason' => "coché mais aucun dev assigné (PO/Scrum/non assigné) — pas de slide",
                ];
                continue;
            }

            $tickets[] = $this->factory->fromIssue($issue, $dev, false);
        }

        // Ordre de présentation : types configurés, puis assigné, puis clé
        usort($tickets, static function (Ticket $a, Ticket $b) use ($order): int {
            $ra = array_search($a->type, $order, true);
            $rb = array_search($b->type, $order, true);
            $ra = $ra === false ? PHP_INT_MAX : $ra;
            $rb = $rb === false ? PHP_INT_MAX : $rb;
            return ($ra <=> $rb)
                ?: strcasecmp($a->assignee, $b->assignee)
                ?: strnatcasecmp($a->key, $b->key);
        });

        foreach ($tickets as $t) {
            $stats['by_dev'][$t->assignee]['role']  = $t->role;
            $stats['by_dev'][$t->assignee]['total'] = ($stats['by_dev'][$t->assignee]['total'] ?? 0) + 1;
            $stats['by_dev'][$t->assignee]['done']  = ($stats['by_dev'][$t->assignee]['done'] ?? 0) + ($t->isDone ? 1 : 0);
        }

        return new SprintReport($sprint, $tickets, $excluded, $stats);
    }
}
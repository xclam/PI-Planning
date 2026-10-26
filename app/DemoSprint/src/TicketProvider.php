<?php

declare(strict_types=1);

namespace DemoSprint;

final class TicketProvider
{
    public function __construct(
        private readonly JiraClient $jira,
        private readonly TicketFactory $factory,
        private readonly array $config,
    ) {
    }

    /** @return Ticket[] filtrés (types/statuts de la config) et triés (type, assigné, clé) */
    public function forSprint(array $sprint): array
    {
        $cfg      = $this->config['tickets'];
        $order    = array_flip($cfg['type_order']);
        $accepted = array_merge($cfg['done_statuses'], $cfg['review_statuses']);

        $tickets = [];
        foreach ($this->jira->getSprintIssues($this->config['jira']['project'], (int) $sprint['id']) as $issue) {
            $type   = trim((string) ($issue['fields']['issuetype']['name'] ?? ''));
            $status = trim((string) ($issue['fields']['status']['name'] ?? ''));
            if (isset($order[$type]) && in_array($status, $accepted, true)) {
                $tickets[] = $this->factory->fromIssue($issue);
            }
        }

        usort($tickets, static fn(Ticket $a, Ticket $b): int =>
            ($order[$a->type] <=> $order[$b->type])
            ?: strcasecmp($a->assignee, $b->assignee)
            ?: strnatcasecmp($a->key, $b->key)
        );

        return $tickets;
    }
}

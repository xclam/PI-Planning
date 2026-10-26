<?php

declare(strict_types=1);

namespace DemoSprint;

final class TicketFactory
{
    public function __construct(private readonly array $config)
    {
    }

    public function fromIssue(array $issue, Person $dev, bool $inferred): Ticket
    {
        $f           = $issue['fields'] ?? [];
        $key         = (string) ($issue['key'] ?? '');
        $rawTitle    = trim((string) ($f['summary'] ?? 'Sans titre'));
        $description = (string) ($f['description'] ?? '');   // wiki markup : pas de strip_tags

        [$si, $title] = $this->parseTitle($rawTitle);

        $cells  = JiraWikiParser::parseTable($description);
        $fields = [];
        foreach ($this->config['labels'] as $id => $label) {
            $raw         = $cells[JiraWikiParser::normalizeLabel($label)] ?? '';
            $fields[$id] = JiraWikiParser::stripMarkup($raw, in_array($id, $this->config['code_fields'], true));
        }

        $status = trim((string) ($f['status']['name'] ?? ''));

        return new Ticket(
            key: $key,
            url: rtrim($this->config['jira']['base_url'], '/') . '/browse/' . $key,
            type: trim((string) ($f['issuetype']['name'] ?? '')),
            status: $status,
            isDone: in_array($status, $this->config['tickets']['done_statuses'], true),
            assignee: $dev->name,
            role: $dev->role->value,
            assigneeInferred: $inferred,
            title: $title,
            si: $si,
            isNew: (bool) preg_match('/\b(new|nouveau)\b/iu', $rawTitle),
            transport: $this->detectTransport($fields, $rawTitle . ' ' . $description),
            fields: $fields,
        );
    }

    /**
     * Titre attendu : "[B4ALL] [SI] [XXX] Libellé" ou "[SI] [XXX] Libellé" (2e tag optionnel).
     * @return array{0:?string,1:string}
     */
    private function parseTitle(string $title): array
    {
        // (?!B4ALL\]) : "[B4ALL] Libellé" ne doit pas être lu comme SI = B4ALL
        $pattern = '/^(?:\[B4ALL\]\s*)?\[(?!B4ALL\])([A-Z0-9\-_]+)\]\s*(?:\[[A-Z0-9\-_]+\]\s*)?/i';

        $si = preg_match($pattern, $title, $m) ? strtoupper($m[1]) : null;
        return [$si, trim((string) preg_replace($pattern, '', $title))];
    }

    private function detectTransport(array $fields, string $haystack): string
    {
        if ($fields['topic'] !== '' || preg_match('/\bkafka\b/i', $haystack)) {
            return 'KAFKA';
        }
        if (preg_match('/\b(files?|fichiers?)\b/iu', $fields['type_flux'] . ' ' . $haystack)) {
            return 'FILE';
        }
        return 'N/C';
    }
}
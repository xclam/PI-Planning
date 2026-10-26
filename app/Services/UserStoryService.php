<?php
declare(strict_types=1);

namespace Services;

use Core\ConfluenceClient;
use Core\JiraClient;
use Models\CreatedUs;
use Models\Pi;

final class UserStoryService
{
    public const FEATURE_PREFIX = '[B4ALL]';
    public const ENABLER_PREFIX = '[DLK]';

    private array $warnings = [];

    public function __construct(
        private readonly JiraClient $jira,
        private readonly ?ConfluenceClient $confluence,
        private readonly string $projectKey,
    ) {
    }

    public static function filterByPrefix(array $issues, string $prefix): array
    {
        return array_values(array_filter(
            $issues,
            static fn(array $i): bool => str_starts_with(trim((string) ($i['fields']['summary'] ?? '')), $prefix)
        ));
    }

    /**
     * @return array{created: array<string,string>, skipped: array<string,string>, warnings: string[], errors: array<string,string>}
     */
    public function createForPi(Pi $pi, array $featureKeys, array $enablerKeys): array
    {
        $result = ['created' => [], 'skipped' => [], 'warnings' => [], 'errors' => []];
        $done   = CreatedUs::mapForPi((int) $pi->id);

        if ($featureKeys && $pi->feature_sprint_id) {
            $this->processBatch('feature', (int) $pi->feature_sprint_id, self::FEATURE_PREFIX, (int) $pi->id, $featureKeys, $done, $result);
        }
        if ($enablerKeys && $pi->enabler_sprint_id) {
            $this->processBatch('enabler', (int) $pi->enabler_sprint_id, self::ENABLER_PREFIX, (int) $pi->id, $enablerKeys, $done, $result);
        }

        $result['warnings'] = $this->warnings;
        return $result;
    }

    private function processBatch(string $kind, int $sprintId, string $prefix, int $piId, array $keys, array $done, array &$result): void
    {
        // On ne traite que des tickets réellement présents dans le sprint du PI (pas de clé arbitraire postée).
        $issues = self::filterByPrefix($this->jira->getSprintIssues($this->projectKey, $sprintId), $prefix);

        foreach ($issues as $issue) {
            $sourceKey = (string) $issue['key'];
            if (!in_array($sourceKey, $keys, true)) {
                continue;
            }
            if (isset($done[$sourceKey])) {
                $result['skipped'][$sourceKey] = $done[$sourceKey];
                continue;
            }

            try {
                $fields = DescriptionParser::parse($issue['fields']['description'] ?? '');

                if ($kind === 'feature') {
                    $fields      = $this->enrichFromConfluence($fields, $sourceKey);
                    $description = $this->featureDescription($fields);
                    $typeId      = (string) config('jira.us_issuetype_id', '10001');
                } else {
                    $description = $this->enablerDescription($fields);
                    $typeId      = (string) config('jira.enabler_issuetype_id', '10007');
                }

                $payload = array_merge([
                    'project'     => ['key' => $this->projectKey],
                    'summary'     => (string) ($issue['fields']['summary'] ?? ''),
                    'description' => $description,
                    'issuetype'   => ['id' => $typeId],
                ], (array) config('jira.us_extra_fields', ['customfield_10107' => ['id' => '10003']]));

                $usKey = (string) ($this->jira->createIssue($payload)['key'] ?? '');
                if ($usKey === '') {
                    throw new \RuntimeException('Jira n\'a pas renvoyé de clé pour la nouvelle US.');
                }

                // Tracé AVANT le lien : un échec de lien ne doit jamais provoquer un doublon à la relance.
                CreatedUs::record($piId, $kind, $sourceKey, $usKey);
                $result['created'][$sourceKey] = $usKey;

                if (!$this->jira->linkIssues($usKey, $sourceKey, 'Relates')) {
                    $this->warnings[] = "{$usKey} créée mais non liée à {$sourceKey} (type de lien introuvable).";
                }
            } catch (\Throwable $e) {
                $result['errors'][$sourceKey] = $e->getMessage();
            }
        }
    }

    private function enrichFromConfluence(array $f, string $sourceKey): array
    {
        if (!$this->confluence) {
            return $f;
        }
        $pageId = ConfluenceClient::extractPageIdFromUrl($f['Nom du flux']);
        if (!$pageId) {
            return $f;
        }

        try {
            $page    = $this->confluence->getPage($pageId);
            $body    = (string) ($page['body']['storage']['value'] ?? '');
            $isEmpty = trim(strip_tags($body)) === '' || strlen(trim($body)) < 50;

            if ($isEmpty) {
                if (config('confluence.init_empty_pages', true)) {
                    $topicOrFile = $f['Topic'] !== '' ? $f['Topic'] : $f['Chorée'];
                    $html = '<p>Documentation initialisée automatiquement pour le flux : ' . htmlspecialchars($f['Nom du flux'], ENT_QUOTES | ENT_SUBSTITUTE) . '</p>'
                          . '<p>Topic / Chorée : ' . htmlspecialchars($topicOrFile, ENT_QUOTES | ENT_SUBSTITUTE) . '</p>';
                    $this->confluence->updatePage(
                        $pageId,
                        (string) ($page['title'] ?? ''),
                        $html,
                        (int) ($page['version']['number'] ?? 1)
                    );
                }
            } else {
                $info = ConfluenceClient::parsePageContent($body);
                if ($info['topic'] !== '') {
                    $f['Topic']        = $info['topic'];
                    $f['Type de flux'] = 'Kafka';
                }
                if ($info['format'] !== null) {
                    $f['Format'] = $info['format'];
                }
            }
        } catch (\Throwable $e) {
            // Ne bloque pas la création Jira, mais on le signale (avant : ignoré silencieusement)
            $this->warnings[] = "{$sourceKey} : enrichissement Confluence impossible ({$e->getMessage()}).";
        }

        return $f;
    }

    private function featureDescription(array $f): string
    {
        [$path, $hive] = $this->targets($f);

        $d  = self::row('Statut cadrage', $f['Statut cadrage'], '🟡');
        $d .= self::row('SI Source', $f['SI Source'], '🏢');
        $d .= self::row('Objectif', $f['Objectif'], '🎯');
        $d .= self::row('Nom du flux', $f['Nom du flux'], '📡');
        $d .= self::row('Type de flux', $f['Type de flux'], '🔄');
        $d .= $f['Type de flux'] === 'Kafka'
            ? self::row('Topic', $f['Topic'])
            : self::row('Chorée', $f['Chorée']);
        $d .= self::row('Initialisation requise', $f['Initialisation requise'], '🚀');
        $d .= self::row('Précisions', $f['Précisions'], '📝');
        $d .= self::row('DCI', $f['DCI'], '📄');
        $d .= self::row('Contacts partenaire', $f['Contacts partenaire'], '🤝');
        $d .= self::row('Planning', $f['Planning'], '📆');
        $d .= self::row('SEI', $f['SEI']);
        $d .= self::row('Format', $f['Format']);
        $d .= self::row('OM', $f['OM']);
        $d .= self::row('PATH', $path);
        $d .= self::row('HIVE', $hive);

        return $d;
    }

    private function enablerDescription(array $f): string
    {
        return self::row('Objectif', $f['Objectif'], '🎯') . self::row('Planning', $f['Planning'], '📆');
    }

    /** @return array{0:string,1:string} [PATH, HIVE] */
    private function targets(array $f): array
    {
        $si   = $f['SI Source'];
        $base = rtrim((string) config('us.hdfs_base', '/projects/PRD/HAS/DLK/trusted-zone'), '/') . '/' . $si;

        if (strtolower(trim($f['SEI'])) === 'oui') {
            return [
                "{$base}/FRA/{om} ET {$base}/SEI/{om}",
                "{$si}_{om}_fra ET {$si}_{om}_sei",
            ];
        }
        return ["{$base}/FRA/{om}", "{$si}_{om}_fra"];
    }

    /** Une ligne de tableau wiki. Les sauts de ligne deviennent `\\` (sinon la ligne casse le tableau). */
    private static function row(string $label, string $value, string $icon = ''): string
    {
        $value = trim($value);
        $value = $value === '' ? '-' : str_replace(["\r\n", "\n", "\r"], ' \\\\ ', $value);

        return '|' . ($icon !== '' ? $icon . ' ' : '') . '*' . $label . '*| ' . $value . " |\n";
    }
}
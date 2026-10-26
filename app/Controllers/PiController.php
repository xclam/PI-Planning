<?php
namespace Controllers;

use Core\View;
use Models\Pi;
use Core\HttpClient;
use Core\JiraClient;
use Core\ConfluenceClient;

class PiController {

    public function index() {
        $pis = Pi::all();

        $activeSprint = null;
        $sprintStats = [];

        try {
            $httpClient = new HttpClient(config('jira.bearer'));
            $jira = new JiraClient($httpClient, config('jira.base_url'));
            // Utilisation du board de l'équipe DataLake pour le sprint en cours
            $boardId = (int) config('jira.team_board_id', 4);
            $projectKey = config('jira.project_key', 'B4A');

            $sprints = $jira->getBoardSprints($boardId);
            
            if (!empty($sprints)) {
                foreach ($sprints as $s) {
                    $state = strtolower(trim($s['state'] ?? ''));
                    if ($state === 'active') {
                        $activeSprint = $s;
                        break;
                    }
                }

                if (!$activeSprint && isset($sprints[0])) {
                    $activeSprint = $sprints[0];
                }

                if ($activeSprint) {
                    $issues = $jira->getSprintIssues($projectKey, (int)$activeSprint['id']);
                    
                    $total = count($issues);
                    $done = 0;
                    $inProgress = 0;
                    $anomalies = 0;
                    
                    foreach ($issues as $issue) {
                        $status = strtolower($issue['fields']['status']['name'] ?? '');
                        $statusCategory = strtolower($issue['fields']['status']['statusCategory']['name'] ?? '');
                        $issueType = strtolower($issue['fields']['issuetype']['name'] ?? '');
                        
                        if (str_contains($statusCategory, 'done') || str_contains($status, 'terminé') || str_contains($status, 'fait')) {
                            $done++;
                        } elseif (str_contains($status, 'cours') || str_contains($status, 'progress')) {
                            $inProgress++;
                        }
                        
                        if (str_contains($issueType, 'anomalie') || str_contains($issueType, 'bug')) {
                            $anomalies++;
                        }
                    }
                    
                    $sprintStats = [
                        'total' => $total,
                        'done' => $done,
                        'in_progress' => $inProgress,
                        'anomalies' => $anomalies
                    ];
                }
            }
        } catch (\Exception $e) {
            // Gestion silencieuse
        }

        View::render('pi/index', [
            'title' => 'Gestion des PI',
            'pis' => $pis,
            'activeSprint' => $activeSprint,
            'sprintStats' => $sprintStats
        ]);
    }

    public function create() {
        $lastPi = Pi::last();

        $nextName = 'PI DI1';
        if ($lastPi && preg_match('/PI DI(\d+)/i', $lastPi->name, $m)) {
            $nextName = 'PI DI' . ((int)$m[1] + 1);
        }

        $sprints = [];
        $errorJira = null;
        try {
            $httpClient = new HttpClient(config('jira.bearer'));
            $jira = new JiraClient($httpClient, config('jira.base_url'));
            $boardId = (int) config('jira.art_board_id');
            $sprints = $jira->getBoardSprints($boardId);
        } catch (\Exception $e) {
            $errorJira = "Impossible de récupérer les sprints Jira : " . $e->getMessage();
        }

        View::render('pi/create', [
            'title' => 'Nouveau Program Increment',
            'nextName' => $nextName,
            'sprints' => $sprints,
            'errorJira' => $errorJira
        ]);
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            
            $featureSprintFull = $_POST['feature_sprint'] ?? '';
            $enablerSprintFull = $_POST['enabler_sprint'] ?? '';

            [$featureSprintId, $featureSprintName] = $featureSprintFull ? explode('|', $featureSprintFull, 2) : [null, null];
            [$enablerSprintId, $enablerSprintName] = $enablerSprintFull ? explode('|', $enablerSprintFull, 2) : [null, null];

            if ($name && $startDate && $endDate) {
                Pi::create([
                    'name' => $name,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'feature_sprint_id' => $featureSprintId ? (int)$featureSprintId : null,
                    'feature_sprint_name' => $featureSprintName,
                    'enabler_sprint_id' => $enablerSprintId ? (int)$enablerSprintId : null,
                    'enabler_sprint_name' => $enablerSprintName,
                ]);
            }

            header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/');
            exit;
        }
    }

    public function show() {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $pi = Pi::find($id);

        if (!$pi) {
            http_response_code(404);
            echo "Program Increment introuvable.";
            return;
        }

        $errorSession = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);

        $sprints = [];
        $features = [];
        $enablers = [];
        $errorJira = null;

        try {
            $httpClient = new HttpClient(config('jira.bearer'));
            $jira = new JiraClient($httpClient, config('jira.base_url'));
            $boardId = (int) config('jira.art_board_id');
            $projectKey = config('jira.project_key', 'B4A');

            $sprints = $jira->getBoardSprints($boardId);

            if ($pi->feature_sprint_id) {
                $rawFeatures = $jira->getSprintIssues($projectKey, (int)$pi->feature_sprint_id);
                $features = array_filter($rawFeatures, function($issue) {
                    $summary = trim($issue['fields']['summary'] ?? '');
                    return str_starts_with($summary, '[B4ALL]');
                });
            }

            if ($pi->enabler_sprint_id) {
                $rawEnablers = $jira->getSprintIssues($projectKey, (int)$pi->enabler_sprint_id);
                $enablers = array_filter($rawEnablers, function($issue) {
                    $summary = trim($issue['fields']['summary'] ?? '');
                    return str_starts_with($summary, '[DLK]');
                });
            }
        } catch (\Exception $e) {
            $errorJira = "Impossible de récupérer les données Jira : " . $e->getMessage();
        }

        View::render('pi/show', [
            'title' => 'Détail du PI : ' . $pi->name,
            'pi' => $pi,
            'sprints' => $sprints,
            'features' => $features,
            'enablers' => $enablers,
            'errorJira' => $errorJira,
            'errorSession' => $errorSession
        ]);
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $pi = Pi::find($id);

            if ($pi) {
                $pi->name = trim($_POST['name'] ?? '');
                $pi->start_date = $_POST['start_date'] ?? '';
                $pi->end_date = $_POST['end_date'] ?? '';
                
                $featureSprintFull = $_POST['feature_sprint'] ?? '';
                $enablerSprintFull = $_POST['enabler_sprint'] ?? '';

                [$featureSprintId, $featureSprintName] = $featureSprintFull ? explode('|', $featureSprintFull, 2) : [null, null];
                [$enablerSprintId, $enablerSprintName] = $enablerSprintFull ? explode('|', $enablerSprintFull, 2) : [null, null];

                $pi->feature_sprint_id = $featureSprintId ? (int)$featureSprintId : null;
                $pi->feature_sprint_name = $featureSprintName;
                $pi->enabler_sprint_id = $enablerSprintId ? (int)$enablerSprintId : null;
                $pi->enabler_sprint_name = $enablerSprintName;

                $pi->save();
            }

            header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/pi/show?id=' . $id);
            exit;
        }
    }

    public function createUs() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/');
            exit;
        }

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $pi = Pi::find($id);

        if (!$pi) {
            http_response_code(404);
            echo "Program Increment introuvable.";
            return;
        }

        $selectedFeatures = $_POST['features'] ?? [];
        $selectedEnablers = $_POST['enablers'] ?? [];

        try {
            $httpClient = new HttpClient(config('jira.bearer'));
            $jira = new JiraClient($httpClient, config('jira.base_url'));
            $projectKey = config('jira.project_key', 'B4A');
            $piName = $pi->name;

            // Parseur multilignes robuste pour les tableaux Jira
            $parseDescription = function(?string $description): array {
                $fields = [
                    'Statut cadrage' => 'N/A',
                    'SI Source' => 'N/A',
                    'Objectif' => 'N/A',
                    'Nom du flux' => 'N/A',
                    'Type de flux' => 'Kafka',
                    'Topic' => '',
                    'Chorée' => '',
                    'Initialisation requise' => 'N/A',
                    'Précisions' => 'N/A',
                    'DCI' => 'N/A',
                    'Contacts partenaire' => 'N/A',
                    'Planning' => 'N/A',
                    'SEI' => 'non',
                    'Format' => 'json',
                    'OM' => '{om}'
                ];
                if (!$description) return $fields;

                $lines = preg_split('/\r\n|\r|\n/', $description);
                $currentKey = null;
                $currentVal = '';

                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (empty($trimmed)) {
                        if ($currentKey !== null) {
                            $currentVal .= "\n";
                        }
                        continue;
                    }

                    if (str_starts_with($trimmed, '|')) {
                        if ($currentKey !== null) {
                            $fields[$currentKey] = trim($currentVal, " |\r\n");
                            $currentKey = null;
                            $currentVal = '';
                        }

                        $parts = explode('|', $trimmed);
                        if (count($parts) >= 3) {
                            $rawKey = trim($parts[1]);
                            $cleanKey = trim(preg_replace('/[^\w\sàâäéèêëîïôöùûüç]/u', '', str_replace('*', '', $rawKey)));
                            $cleanKey = preg_replace('/\s+/', ' ', $cleanKey);

                            $valPart = implode('|', array_slice($parts, 2));

                            $mappingKey = null;
                            if (stripos($cleanKey, 'Statut cadrage') !== false || stripos($cleanKey, 'Statut') !== false) $mappingKey = 'Statut cadrage';
                            elseif (stripos($cleanKey, 'SI Source') !== false) $mappingKey = 'SI Source';
                            elseif (stripos($cleanKey, 'Objectif') !== false) $mappingKey = 'Objectif';
                            elseif (stripos($cleanKey, 'Nom du flux') !== false) $mappingKey = 'Nom du flux';
                            elseif (stripos($cleanKey, 'Type de flux') !== false) $mappingKey = 'Type de flux';
                            elseif (stripos($cleanKey, 'Initialisation requise') !== false || stripos($cleanKey, 'Init') !== false) $mappingKey = 'Initialisation requise';
                            elseif (stripos($cleanKey, 'Precisions') !== false || stripos($cleanKey, 'Précisions') !== false) $mappingKey = 'Précisions';
                            elseif (stripos($cleanKey, 'DCI') !== false) $mappingKey = 'DCI';
                            elseif (stripos($cleanKey, 'Contacts partenaire') !== false) $mappingKey = 'Contacts partenaire';
                            elseif (stripos($cleanKey, 'Planning') !== false) $mappingKey = 'Planning';

                            if ($mappingKey !== null) {
                                $currentKey = $mappingKey;
                                if (str_ends_with($trimmed, '|')) {
                                    $valPart = rtrim($valPart, '|');
                                    $fields[$currentKey] = trim($valPart);
                                    $currentKey = null;
                                    $currentVal = '';
                                } else {
                                    $currentVal = $valPart . "\n";
                                }
                            }
                        }
                    } else {
                        if ($currentKey !== null) {
                            if (str_ends_with($trimmed, '|')) {
                                $currentVal .= rtrim($trimmed, '|');
                                $fields[$currentKey] = trim($currentVal);
                                $currentKey = null;
                                $currentVal = '';
                            } else {
                                $currentVal .= $trimmed . "\n";
                            }
                        }
                    }
                }
                if ($currentKey !== null) {
                    $fields[$currentKey] = trim($currentVal, " |\r\n");
                }

                $typeFluxVal = $fields['Type de flux'] ?? 'Kafka';
                $cleanTypeFlux = str_replace('*', '', $typeFluxVal);
                if (stripos($cleanTypeFlux, 'stream') !== false || stripos($cleanTypeFlux, 'kafka') !== false) {
                    $fields['Type de flux'] = 'Kafka';
                    $fields['Topic'] = trim($cleanTypeFlux);
                } else {
                    $fields['Type de flux'] = 'File';
                    $fields['Chorée'] = trim($cleanTypeFlux);
                }

                $fullText = $description;
                if (stripos($fullText, 'proto') !== false) $fields['Format'] = 'proto';
                elseif (stripos($fullText, 'csv') !== false) $fields['Format'] = 'csv';
                elseif (stripos($fullText, 'json') !== false) $fields['Format'] = 'json';

                if (stripos($fullText, 'SEI : oui') !== false || stripos($fullText, 'SEI oui') !== false || stripos($fullText, 'SEI |oui') !== false) {
                    $fields['SEI'] = 'oui';
                }

                return $fields;
            };

            // 1. Création des US Features
            if (!empty($selectedFeatures)) {
                $rawFeatures = $jira->getSprintIssues($projectKey, (int)$pi->feature_sprint_id);
                
                foreach ($rawFeatures as $feature) {
                    $featureKey = $feature['key'];
                    if (!in_array($featureKey, $selectedFeatures, true)) {
                        continue;
                    }

                    $summary = $feature['fields']['summary'] ?? '';
                    $descFields = $parseDescription($feature['fields']['description'] ?? '');

                    // --- Enrichissement Confluence ---
                    $confluenceUrl = $descFields['Nom du flux'] ?? '';
                    $pageId = ConfluenceClient::extractPageIdFromUrl($confluenceUrl);

                    if ($pageId) {
                        try {
                            // Utilisation des configs centralisées
                            $confHttpClient = new HttpClient(config('confluence.bearer'));
                            $confClient = new ConfluenceClient($confHttpClient, config('confluence.base_url'));
                            
                            $pageData = $confClient->getPage($pageId);
                            
                            $pageTitle = $pageData['title'] ?? '';
                            $currentVersion = $pageData['version']['number'] ?? 1;
                            $bodyStorage = $pageData['body']['storage']['value'] ?? '';

                            if (empty(trim(strip_tags($bodyStorage))) || strlen(trim($bodyStorage)) < 50) {
                                $newHtmlBody = "<p>Documentation initialisée automatiquement pour le flux : {$descFields['Nom du flux']}</p>" .
                                            "<p>Topic / Chorée : " . ($descFields['Topic'] ?? $descFields['Chorée'] ?? '') . "</p>";
                                $confClient->updatePage($pageId, $pageTitle, $newHtmlBody, $currentVersion);
                            } else {
                                $confInfo = ConfluenceClient::parsePageContent($bodyStorage);
                                if (!empty($confInfo['topic'])) {
                                    $descFields['Topic'] = $confInfo['topic'];
                                    $descFields['Type de flux'] = 'Kafka';
                                }
                                if (!empty($confInfo['format'])) {
                                    $descFields['Format'] = $confInfo['format'];
                                }
                            }
                        } catch (\Exception $e) {
                            // Ignorer les erreurs Confluence pour ne pas bloquer la création Jira
                        }
                    }
                    // ---------------------------------

                    $statutCadrage = $descFields['Statut cadrage'];
                    $siSource = $descFields['SI Source'];
                    $objectif = $descFields['Objectif'];
                    $nomFlux = $descFields['Nom du flux'];
                    $typeFlux = $descFields['Type de flux'];
                    $topic = $descFields['Topic'];
                    $choree = $descFields['Chorée'];
                    $init = $descFields['Initialisation requise'];
                    $precisions = $descFields['Précisions'];
                    $dci = $descFields['DCI'];
                    $contacts = $descFields['Contacts partenaire'];
                    $planning = $descFields['Planning'];
                    $sei = $descFields['SEI'];
                    $format = $descFields['Format'];
                    $om = $descFields['OM'];

                    $seiLower = strtolower(trim($sei));
                    if ($seiLower === 'oui') {
                        $path = "/projects/PRD/HAS/DLK/trusted-zone/{$siSource}/FRA/{om} ET /projects/PRD/HAS/DLK/trusted-zone/{$siSource}/SEI/{om}";
                        $hive = "{$siSource}_{om}_fra ET {$siSource}_{om}_sei";
                    } else {
                        $path = "/projects/PRD/HAS/DLK/trusted-zone/{$siSource}/FRA/{om}";
                        $hive = "{$siSource}_{om}_fra";
                    }

                    $usDesc = "|🟡 *Statut cadrage*| {$statutCadrage} |\n" .
                              "|🏢 *SI Source*| {$siSource} |\n" .
                              "|🎯 *Objectif*| {$objectif} |\n" .
                              "|📡 *Nom du flux*| {$nomFlux} |\n" .
                              "|🔄 *Type de flux*| {$typeFlux} |\n";

                    if ($typeFlux === 'Kafka') {
                        $usDesc .= "|*Topic*| {$topic} |\n";
                    } else {
                        $usDesc .= "|*Chorée*| {$choree} |\n";
                    }

                    $usDesc .= "|🚀 *Initialisation requise*| {$init} |\n" .
                               "|📝 *Précisions*| {$precisions} |\n" .
                               "|📄 *DCI*| {$dci} |\n" .
                               "|🤝 *Contacts partenaire*| {$contacts} |\n" .
                               "|📆 *Planning*| {$planning} |\n" .
                               "|*SEI*| {$sei} |\n" .
                               "|*Format*| {$format} |\n" .
                               "|*OM*| {$om} |\n" .
                               "|*PATH*| {$path} |\n" .
                               "|*HIVE*| {$hive} |\n";

                    $payload = [
                        'project' => ['key' => $projectKey],
                        'summary' => $summary,
                        'description' => $usDesc,
                        'issuetype' => ['id' => '10001'],
                        'customfield_10107' => ['id' => '10003']
                    ];

                    $newIssue = $jira->createIssue($payload);
                    if (isset($newIssue['key'])) {
                        $jira->linkIssues($newIssue['key'], $featureKey, 'Relates');
                    }
                }
            }

            // 2. Création des US Enablers
            if (!empty($selectedEnablers)) {
                $rawEnablers = $jira->getSprintIssues($projectKey, (int)$pi->enabler_sprint_id);

                foreach ($rawEnablers as $enabler) {
                    $enablerKey = $enabler['key'];
                    if (!in_array($enablerKey, $selectedEnablers, true)) {
                        continue;
                    }

                    $summary = $enabler['fields']['summary'] ?? '';
                    $descFields = $parseDescription($enabler['fields']['description'] ?? '');

                    $objectif = $descFields['Objectif'];
                    $planning = $descFields['Planning'];

                    $usDesc = "|🎯 *Objectif*| {$objectif} |\n" .
                              "|📆 *Planning*| {$planning} |\n";

                    $payload = [
                        'project' => ['key' => $projectKey],
                        'summary' => $summary,
                        'description' => $usDesc,
                        'issuetype' => ['id' => '10007'],
                        'customfield_10107' => ['id' => '10003']
                    ];

                    $newIssue = $jira->createIssue($payload);
                    if (isset($newIssue['key'])) {
                        $jira->linkIssues($newIssue['key'], $enablerKey, 'Relates');
                    }
                }
            }

        } catch (\Exception $e) {
            $_SESSION['error'] = "Erreur Jira : " . $e->getMessage();
        }

        header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/pi/show?id=' . $id);
        exit;
    }
}
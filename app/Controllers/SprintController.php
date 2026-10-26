<?php
namespace Controllers;

use Core\View;
use Core\HttpClient;
use Core\JiraClient;
use Core\OpenRouter;
use Models\TicketCache;

class SprintController {
    public function show() {
        $sprintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $sprintName = $_GET['name'] ?? 'Sprint';

        $issues = [];
        $myTickets = [];
        $errorJira = null;
        $currentUser = config('jira.username', '');

        try {
            $httpClient = new HttpClient(config('jira.bearer'));
            $jira = new JiraClient($httpClient, config('jira.base_url'));
            $openRouter = new OpenRouter();
            $projectKey = config('jira.project_key', 'B4A');

            if ($sprintId) {
                $issues = $jira->getSprintIssues($projectKey, $sprintId);
                
                foreach ($issues as &$issue) {
                    $assigneeName = $issue['fields']['assignee']['name'] ?? '';
                    $assigneeDisplay = $issue['fields']['assignee']['displayName'] ?? '';
                    $assigneeKey = $issue['fields']['assignee']['key'] ?? '';

                    $isMine = $currentUser && (
                        strcasecmp($assigneeName, $currentUser) === 0 || 
                        strcasecmp($assigneeKey, $currentUser) === 0 || 
                        strcasecmp($assigneeDisplay, $currentUser) === 0
                    );

                    if ($isMine || (!$currentUser && !empty($assigneeName))) {
                        $updatedTime = strtotime($issue['fields']['updated'] ?? '0');
                        $issue['is_recent_24h'] = ($updatedTime >= (time() - 86400));

                        // 1. Récupération des commentaires
                        $comments = [];
                        try {
                            $comments = $jira->getIssueComments($issue['key']);
                            $issue['latest_comment'] = !empty($comments) ? end($comments)['body'] : 'Aucun commentaire';
                        } catch (\Exception $ex) {
                            $issue['latest_comment'] = 'N/A';
                        }

                        // 2. Vérification du cache IA (1 fois par jour max)
                        $cachedAi = TicketCache::getTodayCache($issue['key']);

                        if ($cachedAi !== null) {
                            // Utilisation directe du cache BDD (pas d'appel OpenRouter)
                            $issue['ai_summary'] = $cachedAi['summary'];
                            $issue['ai_action'] = $cachedAi['action'];
                        } else {
                            // Appel OpenRouter si pas de cache aujourd'hui
                            $descRaw = $issue['fields']['description'] ?? '';
                            $descClean = strip_tags($descRaw);
                            $summary = $issue['fields']['summary'] ?? '';

                            $aiAnalysis = $openRouter->getTicketSummaryAndAction($issue['key'], $summary, $descClean, $comments);
                            $issue['ai_summary'] = $aiAnalysis['summary'] ?? '';
                            $issue['ai_action'] = $aiAnalysis['action'] ?? '';

                            // Sauvegarde en BDD pour la journée
                            if (!empty($issue['ai_summary']) || !empty($issue['ai_action'])) {
                                TicketCache::setCache($issue['key'], $issue['ai_summary'], $issue['ai_action']);
                            }
                        }

                        $myTickets[] = $issue;
                    }
                }
                unset($issue);

                // Tri : modifiés dans les dernières 24h en premier
                usort($myTickets, function($a, $b) {
                    return ($b['is_recent_24h'] <=> $a['is_recent_24h']) ?: (strtotime($b['fields']['updated'] ?? 0) <=> strtotime($a['fields']['updated'] ?? 0));
                });
            }
        } catch (\Exception $e) {
            $errorJira = "Erreur lors de la récupération des données du sprint : " . $e->getMessage();
        }

        View::render('sprint/show', [
            'title' => 'Sprint : ' . $sprintName,
            'sprintName' => $sprintName,
            'issues' => $issues,
            'myTickets' => $myTickets,
            'errorJira' => $errorJira
        ]);
    }
}
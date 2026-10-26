<?php
declare(strict_types=1);

namespace Core;

class OpenRouter
{
    private const URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MAX_COMMENTS = 8;
    private const MAX_COMMENT_CHARS = 1500;

    private string $apiKey;
    private string $model;
    private ?string $systemPrompt = null;
    private ?string $content = null;
    private bool $reasoning = false;
    private HttpClient $http;

    public function __construct(?string $apiKey = null, ?string $model = null, ?HttpClient $http = null)
    {
        $this->apiKey = $apiKey ?? (string) config('openrouter.api_key', '');
        $this->model  = $model ?? (string) config('openrouter.model', 'meta-llama/llama-3.3-70b-instruct');
        $this->http   = $http ?? Factory::http($this->apiKey, 60, [
            'HTTP-Referer: http://localhost',
            'X-Title: JiraAutomation',
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->model !== '';
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
    }

    public function setSystemPrompt(string $systemPrompt): void
    {
        $this->systemPrompt = $systemPrompt;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function setReasoning(bool $reasoning): void
    {
        $this->reasoning = $reasoning;
    }

    /** Retourne le texte de la réponse ; lève une exception en cas d'échec. */
    public function chat(): string
    {
        if (!$this->isConfigured() || !$this->content) {
            throw new \RuntimeException('Paramètres OpenRouter manquants.');
        }

        $messages = [];
        if ($this->systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $this->systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $this->content];

        $payload = ['model' => $this->model, 'messages' => $messages, 'temperature' => 0.2];
        if ($this->reasoning) {
            $payload['reasoning'] = ['enabled' => true];
        }

        $res = $this->http->post(self::URL, $payload);
        return (string) ($res['choices'][0]['message']['content'] ?? '');
    }

    /** Analyse d'un ticket : synthèse de l'état actuel + action conseillée. */
    public function getTicketSummaryAndAction(string $ticketKey, string $summary, string $description, array $allComments): array
    {
        $this->setSystemPrompt(
            "Tu es un assistant Product Owner. Ton rôle est d'analyser l'état ACTUEL d'un ticket Jira.\n\n" .
            "RÈGLES D'ANALYSE STRICTES :\n" .
            "1. PRIORITÉ ABSOLUE AU DERNIER COMMENTAIRE : Le dernier commentaire prévaut sur la description et sur les commentaires précédents.\n" .
            "2. PAS D'HALLUCINATION : Ne mentionne QUE des éléments explicitement présents dans le texte fourni. N'invente pas de noms de composants, de serveurs ou de sujets.\n" .
            "3. Ne propose JAMAIS une action déjà réalisée ou un doublon.\n\n" .
            "RÉPONSE ATTENDUE (JSON strict uniquement) :\n" .
            "{\n" .
            "  \"summary\": \"Synthèse ultra-courte de l'état ACTUEL basé sur le dernier événement (max 15 mots).\",\n" .
            "  \"action\": \"Action/Relance concrète, précise et immédiate à faire aujourd'hui (max 10 mots).\"\n" .
            "}"
        );

        $timeline  = "--- DESCRIPTIF DU TICKET ---\n" . (trim($description) !== '' ? mb_substr($description, 0, 4000) : 'Aucune description') . "\n\n";
        $timeline .= "--- HISTORIQUE DES COMMENTAIRES (CHRONOLOGIQUE) ---\n";

        if (!$allComments) {
            $timeline .= "Aucun commentaire.\n";
        } else {
            $omitted  = max(0, count($allComments) - self::MAX_COMMENTS);
            $comments = array_slice($allComments, -self::MAX_COMMENTS);
            if ($omitted > 0) {
                $timeline .= "({$omitted} commentaires plus anciens omis)\n\n";
            }
            $i = 1;
            foreach ($comments as $comment) {
                $author = $comment['author']['displayName'] ?? $comment['author']['name'] ?? 'Inconnu';
                $date   = isset($comment['created']) ? date('d/m/Y H:i', strtotime($comment['created'])) : '';
                $body   = mb_substr(trim((string) ($comment['body'] ?? '')), 0, self::MAX_COMMENT_CHARS);
                $timeline .= "[Événement {$i}] - {$date} par {$author} :\n{$body}\n\n";
                $i++;
            }
            $timeline .= '⚠️ ATTENTION : L\'ÉVÉNEMENT ' . ($i - 1) . " EST LE DERNIER EN DATE. BASE TA SYNTHÈSE SUR CET ÉTAT FINAL.\n";
        }

        $this->setContent("TICKET : {$ticketKey} - {$summary}\n\n{$timeline}");

        try {
            $raw = $this->chat();
        } catch (\Throwable $e) {
            error_log("OpenRouter ({$ticketKey}) : " . $e->getMessage());
            return ['summary' => '', 'action' => ''];
        }

        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw));
        $data  = json_decode((string) $clean, true);
        if (!is_array($data) && preg_match('/\{.*\}/s', $raw, $m)) {
            $data = json_decode($m[0], true); // le modèle a ajouté du texte autour du JSON
        }

        if (is_array($data) && isset($data['summary'], $data['action'])) {
            return ['summary' => trim((string) $data['summary']), 'action' => trim((string) $data['action'])];
        }
        return ['summary' => '', 'action' => ''];
    }
}
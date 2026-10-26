<?php
namespace Core;

class OpenRouter {
    private string $apiKey;
    private string $url = 'https://openrouter.ai/api/v1/chat/completions';
    private string $model;
    private ?string $systemPrompt = null;
    private ?string $content = null;
    private bool $reasoning = false;

    public function __construct(?string $apiKey = null, ?string $model = null) {
        $this->apiKey = $apiKey ?? config('openrouter.api_key', '');
        $this->model = $model ?? config('openrouter.model', 'meta-llama/llama-3.3-70b-instruct');
    }

    public function setModel(string $model): void {
        $this->model = $model;
    }

    public function setSystemPrompt(string $systemPrompt): void {
        $this->systemPrompt = $systemPrompt;
    }

    public function setContent(string $content): void {
        $this->content = $content;
    }

    public function setReasoning(bool $reasoning): void {
        $this->reasoning = $reasoning;
    }

    public function request(): array {
        if (!$this->apiKey || !$this->model || !$this->content) {
            return ['error' => 'Paramètres manquants'];
        }

        $messages = [];
        if ($this->systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $this->systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $this->content];

        $payloadData = [
            'model' => $this->model,
            'messages' => $messages
        ];

        if ($this->reasoning) {
            $payloadData['reasoning'] = ['enabled' => true];
        }

        $payload = json_encode($payloadData);
        $ch = curl_init($this->url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: http://localhost',
                'X-Title: JiraAutomation'
            ],
            CURLOPT_POSTFIELDS => $payload
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [
            'response' => $response,
            'httpCode' => $httpCode,
            'curlError' => $curlError
        ];
    }

    /**
     * Analyse un ticket Jira avec son historique pour extraire la synthèse et l'action conseillée
     */
    public function getTicketSummaryAndAction(string $ticketKey, string $summary, string $description, array $allComments): array {
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

        $timeline = "--- DESCRIPTIF DU TICKET ---\n" . ($description ?: "Aucune description") . "\n\n";
        $timeline .= "--- HISTORIQUE DES COMMENTAIRES (CHRONOLOGIQUE) ---\n";

        if (empty($allComments)) {
            $timeline .= "Aucun commentaire.\n";
        } else {
            $i = 1;
            foreach ($allComments as $comment) {
                $author = $comment['author']['displayName'] ?? $comment['author']['name'] ?? 'Inconnu';
                $date = isset($comment['created']) ? date('d/m/Y H:i', strtotime($comment['created'])) : '';
                $body = trim($comment['body'] ?? '');
                
                $timeline .= "[Événement {$i}] - {$date} par {$author} :\n{$body}\n\n";
                $i++;
            }
            $timeline .= "⚠️ ATTENTION : L'ÉVÉNEMENT " . ($i - 1) . " EST LE DERNIER EN DATE. BASE TA SYNTHÈSE SUR CET ÉTAT FINAL.\n";
        }

        $prompt = "TICKET : {$ticketKey} - {$summary}\n\n{$timeline}";
        $this->setContent($prompt);

        $res = $this->request();

        if ($res['httpCode'] === 200 && !empty($res['response'])) {
            $jsonResponse = json_decode($res['response'], true);
            $content = $jsonResponse['choices'][0]['message']['content'] ?? '';
            
            $cleanJson = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
            $data = json_decode($cleanJson, true);

            if (is_array($data) && isset($data['summary'], $data['action'])) {
                return $data;
            }
        }

        return ['summary' => '', 'action' => ''];
    }
}
<?php 
namespace Core;

class ConfluenceClient {
    private HttpClient $http;
    private string $api;

    public function __construct(HttpClient $http, string $baseUrl) {
        $this->http = $http;
        $this->api = rtrim($baseUrl, '/') . '/rest/api/';
    }

    public function getPage(string $pageId): array {
        return $this->http->get($this->api . 'content/' . $pageId . '?expand=body.storage,version');
    }

    public function updatePage(string $pageId, string $title, string $htmlBody, int $currentVersion): array {
        $payload = [
            'type' => 'page',
            'title' => $title,
            'body' => [
                'storage' => [
                    'value' => $htmlBody,
                    'representation' => 'storage'
                ]
            ],
            'version' => [
                'number' => $currentVersion + 1
            ]
        ];

        return $this->http->put($this->api . 'content/' . $pageId, $payload);
    }

    public static function extractPageIdFromUrl(string $text): ?string {
        if (preg_match('/pageId=(\d+)/', $text, $matches)) {
            return $matches[1];
        }
        if (preg_match('/\/pages\/(\d+)/', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Analyse le contenu HTML/XHTML stocké de Confluence pour en extraire les informations techniques
     */
    public static function parsePageContent(string $htmlBody): array {
        $data = [
            'topic' => '',
            'choree' => '',
            'type_flux' => 'Kafka',
            'format' => 'json',
            'oms' => [],
            'path_hdfs' => '',
            'table_hive' => ''
        ];

        // 1. Extraction du Topic (souvent dans les macros info ou balises strong après "Préciser le nom de la topic")
        if (preg_match('/Préciser le nom de la topic.*?<strong>(.*?)<\/strong>/is', $htmlBody, $matches)) {
            $topicVal = trim(strip_tags($matches[1]));
            if (!empty($topicVal)) {
                $data['topic'] = $topicVal;
                $data['type_flux'] = 'Kafka';
            }
        }

        // 2. Extraction du chemin HDFS de la trusted zone ou landing zone
        if (preg_match('/\/projects\/[A-Z]+\/HAS\/DLK\/trusted-zone\/([^\/<]+)\/FRA\/([^\/<]+)/i', $htmlBody, $matches)) {
            // $matches[1] correspond au SI, $matches[2] à l'OM potentiel ou dossier
            $data['path_hdfs'] = trim($matches[0]);
        }

        // 3. Extraction de la table Hive
        if (preg_match('/<p>\s*([a-z0-9_]+_fra)\s*<\/p>/i', $htmlBody, $matches)) {
            $data['table_hive'] = trim($matches[1]);
        }

        // 4. Déduction du format (json, proto, csv)
        $lowerBody = strtolower($htmlBody);
        if (str_contains($lowerBody, 'proto') || str_contains($lowerBody, 'protobuf')) {
            $data['format'] = 'proto';
        } elseif (str_contains($lowerBody, 'csv')) {
            $data['format'] = 'csv';
        } else {
            $data['format'] = 'json';
        }

        return $data;
    }
}
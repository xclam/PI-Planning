<?php
declare(strict_types=1);

namespace Core;

final class ConfluenceClient
{
    private string $api;

    public function __construct(private readonly HttpClient $http, string $baseUrl)
    {
        $this->api = rtrim($baseUrl, '/') . '/rest/api/';
    }

    public function getPage(string $pageId): array
    {
        return $this->http->get($this->api . 'content/' . rawurlencode($pageId), ['expand' => 'body.storage,version']);
    }

    public function updatePage(string $pageId, string $title, string $htmlBody, int $currentVersion): array
    {
        return $this->http->put($this->api . 'content/' . rawurlencode($pageId), [
            'type'    => 'page',
            'title'   => $title,
            'body'    => ['storage' => ['value' => $htmlBody, 'representation' => 'storage']],
            'version' => ['number' => $currentVersion + 1],
        ]);
    }

    public static function extractPageIdFromUrl(string $text): ?string
    {
        if (preg_match('/pageId=(\d+)/', $text, $m) || preg_match('#/pages/(\d+)#', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extrait les infos techniques du stockage XHTML Confluence.
     * `format` vaut null si rien n'est détecté explicitement (avant : 'json' par défaut,
     * ce qui écrasait systématiquement le format lu dans Jira).
     */
    public static function parsePageContent(string $htmlBody): array
    {
        $data = ['topic' => '', 'format' => null, 'path_hdfs' => '', 'table_hive' => ''];

        if (preg_match('/Préciser le nom de la topic.{0,800}?<strong>(.*?)<\/strong>/is', $htmlBody, $m)) {
            $data['topic'] = trim(strip_tags($m[1]));
        }

        if (preg_match('#/projects/[A-Z]+/HAS/DLK/trusted-zone/[^/<]+/FRA/[^/<\s]+#i', $htmlBody, $m)) {
            $data['path_hdfs'] = trim($m[0]);
        }

        if (preg_match('/<p>\s*([a-z0-9_]+_fra)\s*<\/p>/i', $htmlBody, $m)) {
            $data['table_hive'] = trim($m[1]);
        }

        $lower = strtolower(strip_tags($htmlBody));
        if (preg_match('/\b(proto(?:buf)?)\b/', $lower)) {
            $data['format'] = 'proto';
        } elseif (preg_match('/\bcsv\b/', $lower)) {
            $data['format'] = 'csv';
        } elseif (preg_match('/\bjson\b/', $lower)) {
            $data['format'] = 'json';
        }

        return $data;
    }
}
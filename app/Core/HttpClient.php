<?php
declare(strict_types=1);

namespace Core;

use JsonException;
use RuntimeException;

final class HttpClient
{
    public function __construct(
        private readonly string $token,
        private readonly int $timeout = 30,
        private readonly ?string $caBundle = null,
        private readonly bool $verifySsl = true,
        private readonly array $extraHeaders = [],
    ) {
    }

    public function get(string $url, array $query = []): array
    {
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $this->request('GET', $url);
    }

    public function post(string $url, array $payload): array
    {
        return $this->request('POST', $url, $payload);
    }

    public function put(string $url, array $payload): array
    {
        return $this->request('PUT', $url, $payload);
    }

    private function request(string $method, string $url, ?array $payload = null): array
    {
        $ch = curl_init($url);

        $options = [
            // CUSTOMREQUEST : avant, un PUT partait en POST (CURLOPT_POST forcé) → mise à jour Confluence en échec
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_HTTPHEADER     => array_merge([
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json',
            ], $this->extraHeaders),
        ];
        if ($this->caBundle !== null && $this->caBundle !== '') {
            $options[CURLOPT_CAINFO] = $this->caBundle;
        }
        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $options);
        $body  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        unset($ch);

        if ($body === false) {
            throw new RuntimeException("Erreur réseau ($method $url) : $error");
        }
        if ($code >= 400) {
            throw new RuntimeException(sprintf('HTTP %d sur %s %s : %s', $code, $method, $url, mb_substr((string) $body, 0, 300)));
        }

        // Jira répond 201/204 SANS corps sur issueLink : ce n'est pas une erreur.
        // (Avant : JsonException → le lien était déjà créé, mais on réessayait avec un autre type → doublons.)
        if (trim((string) $body) === '') {
            return [];
        }

        try {
            $decoded = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Réponse JSON invalide ($method $url) : " . $e->getMessage(), 0, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
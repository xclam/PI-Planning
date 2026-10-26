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
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];
        if ($this->caBundle !== null) {
            $options[CURLOPT_CAINFO] = $this->caBundle;
        }
        if ($payload !== null) {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($ch, $options);
        $body  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("Erreur réseau ($method $url) : $error");
        }
        if ($code >= 400) {
            throw new RuntimeException(sprintf('HTTP %d sur %s %s : %s', $code, $method, $url, mb_substr((string) $body, 0, 300)));
        }

        try {
            return json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Réponse JSON invalide ($method $url) : " . $e->getMessage(), 0, $e);
        }
    }
}
<?php
declare(strict_types=1);

namespace Core;

/** Centralise la construction des clients (config, TLS, timeouts). */
final class Factory
{
    public static function http(string $token, ?int $timeout = null, array $headers = []): HttpClient
    {
        $ca = config('http.ca_bundle');
        return new HttpClient(
            $token,
            $timeout ?? (int) config('http.timeout', 30),
            is_string($ca) && $ca !== '' ? $ca : null,
            (bool) config('http.verify_ssl', true),
            $headers
        );
    }

    public static function jira(): JiraClient
    {
        return new JiraClient(self::http((string) config('jira.bearer', '')), (string) config('jira.base_url', ''));
    }

    public static function confluence(): ?ConfluenceClient
    {
        $base   = (string) config('confluence.base_url', '');
        $bearer = (string) config('confluence.bearer', '');
        if ($base === '' || $bearer === '') {
            return null;
        }
        return new ConfluenceClient(self::http($bearer), $base);
    }

    public static function openRouter(): OpenRouter
    {
        return new OpenRouter();
    }
}
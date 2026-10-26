<?php

declare(strict_types=1);

namespace DemoSprint;

/**
 * Parse la description Jira (wiki markup, API v2) : tableaux `| *Libellé* | valeur |`.
 * Gère les valeurs multi-lignes et les liens [texte|url] contenant des "|".
 */
final class JiraWikiParser
{
    /** @return array<string,string> libellé normalisé (minuscules) => valeur brute */
    public static function parseTable(string $wiki): array
    {
        $rows = [];
        $last = null;

        foreach (preg_split('/\R/', $wiki) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                $last = null;
                continue;
            }
            if ($line[0] === '|') {
                $masked = preg_replace_callback('/\[[^\]]*\]/', fn(array $m) => str_replace('|', "\x01", $m[0]), $line);
                $cells  = array_map(
                    fn(string $c) => trim(str_replace("\x01", '|', $c)),
                    explode('|', trim((string) $masked, '|'))
                );
                if (count($cells) >= 2) {
                    $last        = self::normalizeLabel($cells[0]);
                    $rows[$last] = $cells[1];
                } else {
                    $last = null;
                }
            } elseif ($last !== null) {
                $rows[$last] .= "\n" . rtrim($line, '| ');  // suite d'une cellule multi-lignes
            }
        }
        return $rows;
    }

    /**
     * Forme canonique d'un libellé pour comparaison : sans markup, sans icône/emoji/:shortcode:
     * ni ponctuation en début/fin ("🎯 Objectif", "*Objectif :*" => "objectif"), casse et espaces normalisés.
     * À appliquer des deux côtés (libellé Jira ET libellé de la config).
     */
    public static function normalizeLabel(string $label): string
    {
        $label = self::stripMarkup($label);
        $label = (string) preg_replace('/^(?::[\w+-]+:\s*)+/u', '', $label);   // :dart:
        $label = (string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $label);       // emoji, symboles, espaces (y c. NBSP)
        $label = (string) preg_replace('/[^\p{L}\p{N}]+$/u', '', $label);       // ":" ou emoji final
        $label = (string) preg_replace('/[\s\x{00A0}]+/u', ' ', $label);

        return mb_strtolower($label);
    }

    public static function stripMarkup(string $text, bool $keepAsterisks = false): string
    {
        $text = (string) preg_replace('/\{color:[^}]+\}(.*?)\{color\}/is', '$1', $text);
        $text = (string) preg_replace('/\[([^\]|]+)\|[^\]]+\]/', '$1', $text);   // [texte|url] => texte
        $text = (string) preg_replace('/\{\{(.*?)\}\}/s', '$1', $text);          // {{mono}}
        $text = (string) preg_replace('/^h[1-6]\.\s+/m', '', $text);
        $text = str_replace(['{*}', '{ * }'], '', $text);
        if (!$keepAsterisks) {
            // retire le gras *mot* mais pas les jokers de chemin type /data/*/input
            $text = (string) preg_replace('/(?<![\w\/])\*(?=\S)(.+?)(?<=\S)\*(?![\w\/])/u', '$1', $text);
        }
        return trim($text, " \t\n\r\0\x0B\u{00A0}");   // trim Unicode-safe (NBSP)
    }
}
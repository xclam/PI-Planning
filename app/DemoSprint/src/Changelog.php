<?php

declare(strict_types=1);

namespace DemoSprint;

use DateTimeImmutable;
use Exception;

/** Lecture de l'historique d'un ticket (search avec expand=changelog). */
final class Changelog
{
    /**
     * @return array<int,array{created:DateTimeImmutable,from:?string,to:?string,fromString:?string,toString:?string}>
     *         changements du champ demandé, du plus ancien au plus récent
     */
    public static function changes(array $issue, string $field): array
    {
        $out = [];
        foreach ($issue['changelog']['histories'] ?? [] as $history) {
            $created = self::date($history['created'] ?? null);
            if ($created === null) {
                continue;
            }
            foreach ($history['items'] ?? [] as $item) {
                if (strcasecmp((string) ($item['field'] ?? ''), $field) === 0) {
                    $out[] = [
                        'created'    => $created,
                        'from'       => $item['from'] ?? null,
                        'to'         => $item['to'] ?? null,
                        'fromString' => $item['fromString'] ?? null,
                        'toString'   => $item['toString'] ?? null,
                    ];
                }
            }
        }
        usort($out, static fn(array $a, array $b): int => $a['created'] <=> $b['created']);
        return $out;
    }

    public static function date(?string $iso): ?DateTimeImmutable
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($iso);
        } catch (Exception) {
            return null;
        }
    }

    /** "123, 124" => ['123','124'] */
    public static function ids(?string $csv): array
    {
        return preg_split('/\s*,\s*/', trim((string) $csv), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}

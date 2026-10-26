<?php
declare(strict_types=1);

namespace Services;

/** Parse le tableau wiki Jira (|*Clé*| valeur |) des descriptions de features/enablers. */
final class DescriptionParser
{
    private const DEFAULTS = [
        'Statut cadrage'         => 'N/A',
        'SI Source'              => 'N/A',
        'Objectif'               => 'N/A',
        'Nom du flux'            => 'N/A',
        'Type de flux'           => 'Kafka',
        'Topic'                  => '',
        'Chorée'                 => '',
        'Initialisation requise' => 'N/A',
        'Précisions'             => 'N/A',
        'DCI'                    => 'N/A',
        'Contacts partenaire'    => 'N/A',
        'Planning'               => 'N/A',
        'SEI'                    => 'non',
        'Format'                 => 'json',
        'OM'                     => '{om}',
    ];

    /** Libellé normalisé (sans accents/emoji/étoiles) → champ. L'ordre compte. */
    private const FIELD_MAP = [
        'statut'              => 'Statut cadrage',
        'si source'           => 'SI Source',
        'objectif'            => 'Objectif',
        'nom du flux'         => 'Nom du flux',
        'type de flux'        => 'Type de flux',
        'init'                => 'Initialisation requise',
        'precision'           => 'Précisions',
        'dci'                 => 'DCI',
        'contacts partenaire' => 'Contacts partenaire',
        'planning'            => 'Planning',
    ];

    public static function parse(?string $description): array
    {
        $fields = self::DEFAULTS;
        if ($description === null || trim($description) === '') {
            return $fields;
        }

        $current = null;
        $buffer  = [];
        $flush   = static function () use (&$current, &$buffer, &$fields): void {
            if ($current !== null) {
                $fields[$current] = trim(implode("\n", $buffer), " |\r\n\t");
            }
            $current = null;
            $buffer  = [];
        };

        foreach (preg_split('/\R/u', $description) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($current !== null) {
                    $buffer[] = '';
                }
                continue;
            }

            if ($trimmed[0] === '|') {
                $flush();
                $parts = explode('|', $trimmed);
                if (count($parts) < 3 || ($field = self::resolveField($parts[1])) === null) {
                    continue;
                }
                $current = $field;
                $buffer  = [implode('|', array_slice($parts, 2))];
                if (str_ends_with($trimmed, '|')) {
                    $flush();
                }
            } elseif ($current !== null) {
                $buffer[] = $trimmed;
                if (str_ends_with($trimmed, '|')) {
                    $flush();
                }
            }
        }
        $flush();

        // Kafka vs fichier
        $typeFlux = str_replace('*', '', $fields['Type de flux']);
        if (stripos($typeFlux, 'stream') !== false || stripos($typeFlux, 'kafka') !== false) {
            $fields['Type de flux'] = 'Kafka';
            $fields['Topic']        = trim($typeFlux);
        } else {
            $fields['Type de flux'] = 'File';
            $fields['Chorée']       = trim($typeFlux);
        }

        // Format et SEI détectés sur le texte complet
        if (stripos($description, 'proto') !== false) {
            $fields['Format'] = 'proto';
        } elseif (stripos($description, 'csv') !== false) {
            $fields['Format'] = 'csv';
        } elseif (stripos($description, 'json') !== false) {
            $fields['Format'] = 'json';
        }

        if (preg_match('/SEI\s*[:|]?\s*oui/i', $description)) {
            $fields['SEI'] = 'oui';
        }

        return $fields;
    }

    private static function resolveField(string $rawLabel): ?string
    {
        $label = preg_replace('/[^\p{L}\p{N}\s]/u', '', $rawLabel) ?? '';
        $label = mb_strtolower(preg_replace('/\s+/u', ' ', trim($label)) ?? '');
        $label = strtr($label, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        foreach (self::FIELD_MAP as $needle => $field) {
            if (str_contains($label, $needle)) {
                return $field;
            }
        }
        return null;
    }
}
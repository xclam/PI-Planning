<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/** Trace des US créées : rend la création idempotente et affichable dans l'UI. */
class CreatedUs extends Model
{
    protected static ?string $table = 'created_us';

    /** @return array<string,string> clé du ticket source => clé de l'US créée */
    public static function mapForPi(int $piId): array
    {
        $map = [];
        foreach (static::where(['pi_id' => $piId]) as $row) {
            $map[(string) $row->source_key] = (string) $row->us_key;
        }
        return $map;
    }

    public static function record(int $piId, string $kind, string $sourceKey, string $usKey): void
    {
        static::create([
            'pi_id'      => $piId,
            'kind'       => $kind,
            'source_key' => $sourceKey,
            'us_key'     => $usKey,
        ]);
    }
}
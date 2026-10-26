<?php
declare(strict_types=1);

namespace Core;

final class Config
{
    private static ?array $settings = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        // Surcharge par variable d'environnement (secrets hors fichier)
        $env = getenv('PIPLANNING_' . strtoupper(str_replace('.', '_', $key)));
        if ($env !== false && $env !== '') {
            return match (strtolower($env)) {
                'true'  => true,
                'false' => false,
                default => $env,
            };
        }

        if (self::$settings === null) {
            $file = __DIR__ . '/../../config/config.php';
            self::$settings = is_file($file) ? (require $file) : [];
        }

        $node = self::$settings;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }

        return $node ?? $default;
    }
}
<?php
declare(strict_types=1);

namespace Core;

use RuntimeException;

final class View
{
    public static function render(string $viewPath, array $data = [], ?string $layout = 'base'): void
    {
        $content = self::capture($viewPath, $data);

        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::capture('layouts/' . $layout, $data + ['content' => $content]);
    }

    public static function partial(string $viewPath, array $data = []): void
    {
        echo self::capture($viewPath, $data);
    }

    private static function capture(string $viewPath, array $data): string
    {
        if (!preg_match('#^[A-Za-z0-9_/\-]+$#', $viewPath)) {
            throw new RuntimeException("Nom de vue invalide : {$viewPath}");
        }
        $file = __DIR__ . '/../Views/' . $viewPath . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Vue introuvable : {$viewPath}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file; // `require` (et non require_once) : une vue peut être rendue plusieurs fois
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
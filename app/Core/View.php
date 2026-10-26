<?php
namespace Core;

class View {
    public static function render(string $viewPath, array $data = [], string $layout = 'base') {
        extract($data);

        ob_start();
        require_once __DIR__ . "/../Views/{$viewPath}.php";
        $content = ob_get_clean();

        require_once __DIR__ . "/../Views/layouts/{$layout}.php";
    }
}
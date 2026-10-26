<?php
namespace Core;

class Config 
{
    private static ?array $settings = null;

    public static function get(string $key, $default = null) {
        if (self::$settings === null) {
            $file = __DIR__ . '/../../config/config.php';
            self::$settings = file_exists($file) ? require $file : [];
        }

        $array = self::$settings;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }
}
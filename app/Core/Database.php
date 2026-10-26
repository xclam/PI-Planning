<?php
declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        // Aucun identifiant par défaut en dur : tout vient de la config / de l'environnement.
        $host = (string) config('db.host', 'localhost');
        $port = (int) config('db.port', 3306);
        $name = (string) config('db.name', '');
        $user = (string) config('db.user', '');
        $pass = (string) config('db.pass', '');

        if ($name === '' || $user === '') {
            throw new RuntimeException('Configuration base de données incomplète (clé "db" de config/config.php).');
        }

        try {
            self::$instance = new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('Connexion MySQL : ' . $e->getMessage());
            throw new RuntimeException('Connexion à la base de données impossible.', 0, $e);
        }

        return self::$instance;
    }
}
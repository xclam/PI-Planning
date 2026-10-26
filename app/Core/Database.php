<?php
namespace Core;

use PDO;
use PDOException;
use RuntimeException;

class Database {
    private static ?PDO$instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            // Récupération sécurisée avec valeurs par défaut basées sur ta config
            $host    = function_exists('config') ? config('db.host', 'localhost') : 'localhost';
            $dbname  = function_exists('config') ? config('db.name', 'piplanning') : 'piplanning';
            $user    = function_exists('config') ? config('db.user', 'piplanning') : 'piplanning';
            $pass    = function_exists('config') ? config('db.pass', 'g4Vexo') : 'g4Vexo';
            $charset = 'utf8mb4';

            // Construction explicite du DSN avec obligatoirement le dbname
            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException("Erreur de connexion DSN [{$dsn}] : " . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
<?php
namespace Core;

use PDO;
use Exception;

abstract class Model {
    protected static ?string $table = null;
    protected static string $primaryKey = 'id';
    protected array $attributes = [];
    protected array $original = [];

    public function __construct(array $attributes = []) {
        $this->fill($attributes);
    }

    /** Détermine automatiquement le nom de la table si non défini */
    public static function getTableName(): string {
        if (static::$table !== null) {
            return static::$table;
        }
        $parts = explode('\\', static::class);
        $className = end($parts);
        return strtolower($className) . 's'; // Ex: Pi -> pis
    }

    /** Hydrate le modèle avec un tableau de données */
    public function fill(array $attributes): self {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    /** Accès magique aux propriétés ($model->name) */
    public function __get(string $key) {
        return $this->attributes[$key] ?? null;
    }

    /** Définition magique des propriétés ($model->name = 'value') */
    public function __set(string $key, $value): void {
        $this->attributes[$key] = $value;
    }

    /** Récupère tous les enregistrements */
    public static function all(): array {
        $db = Database::getConnection();
        $table = static::getTableName();
        $stmt = $db->query("SELECT * FROM {$table} ORDER BY " . static::$primaryKey . " DESC");
        
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[] = new static($row);
        }
        return $results;
    }

    /** Trouve un enregistrement par sa clé primaire */
    public static function find($id): ?static {
        $db = Database::getConnection();
        $table = static::getTableName();
        $key = static::$primaryKey;
        
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE {$key} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? new static($row) : null;
    }

    /** Récupère le dernier enregistrement */
    public static function last(): ?static {
        $db = Database::getConnection();
        $table = static::getTableName();
        $key = static::$primaryKey;
        
        $stmt = $db->query("SELECT * FROM {$table} ORDER BY {$key} DESC LIMIT 1");
        $row = $stmt->fetch();

        return $row ? new static($row) : null;
    }

    /** Méthode statique pour créer et sauvegarder en une ligne */
    public static function create(array $attributes): static {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /** Sauvegarde automatique (INSERT si nouveau, UPDATE si existant) */
    public function save(): bool {
        $db = Database::getConnection();
        $table = static::getTableName();
        $key = static::$primaryKey;

        // Si la clé primaire est présente et non nulle, on fait un UPDATE
        if (isset($this->attributes[$key]) && !empty($this->attributes[$key])) {
            $fields = [];
            $params = [];
            foreach ($this->attributes as $field => $value) {
                if ($field === $key) continue;
                $fields[] = "`{$field}` = :{$field}";
                $params[$field] = $value;
            }
            $params[$key] = $this->attributes[$key];

            $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE {$key} = :{$key}";
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } 

        // Sinon, on fait un INSERT
        $fields = array_keys($this->attributes);
        $placeholders = array_map(fn($f) => ":{$f}", $fields);

        $sql = "INSERT INTO {$table} (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $db->prepare($sql);
        $success = $stmt->execute($this->attributes);

        if ($success) {
            $this->attributes[$key] = (int) $db->lastInsertId();
        }

        return $success;
    }

    /** Supprime l'enregistrement courant */
    public function delete(): bool {
        $key = static::$primaryKey;
        if (!isset($this->attributes[$key])) {
            return false;
        }

        $db = Database::getConnection();
        $table = static::getTableName();
        $stmt = $db->prepare("DELETE FROM {$table} WHERE {$key} = :id");
        return $stmt->execute(['id' => $this->attributes[$key]]);
    }

    /** Convertit les attributs en tableau natif */
    public function toArray(): array {
        return $this->attributes;
    }
}
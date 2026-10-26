<?php
declare(strict_types=1);

namespace Core;

use InvalidArgumentException;

abstract class Model
{
    protected static ?string $table = null;
    protected static string $primaryKey = 'id';

    protected array $attributes = [];
    protected array $original = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function getTableName(): string
    {
        if (static::$table !== null) {
            return static::$table;
        }
        $parts = explode('\\', static::class);
        return strtolower(end($parts)) . 's';
    }

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /** Nécessaire pour que `$model->champ ?? $defaut` fonctionne. */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /** Protège les noms de colonnes/tables interpolés dans le SQL. */
    private static function quote(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("Identifiant SQL invalide : {$identifier}");
        }
        return '`' . $identifier . '`';
    }

    protected static function hydrate(array $row): static
    {
        $model = new static($row);
        $model->original = $row;
        return $model;
    }

    /** @return static[] */
    public static function all(): array
    {
        return static::where([], static::$primaryKey, 'DESC');
    }

    /** @return static[] */
    public static function where(array $criteria, ?string $orderBy = null, string $direction = 'ASC'): array
    {
        $sql    = 'SELECT * FROM ' . self::quote(static::getTableName());
        $params = [];
        $conds  = [];

        foreach ($criteria as $column => $value) {
            $placeholder          = 'w' . count($params);
            $conds[]              = self::quote((string) $column) . ' = :' . $placeholder;
            $params[$placeholder] = $value;
        }
        if ($conds) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . self::quote($orderBy) . (strtoupper($direction) === 'DESC' ? ' DESC' : ' ASC');
        }

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn(array $row): static => static::hydrate($row), $stmt->fetchAll());
    }

    public static function find(int|string $id): ?static
    {
        return static::findBy([static::$primaryKey => $id]);
    }

    public static function findBy(array $criteria): ?static
    {
        $rows = static::where($criteria);
        return $rows[0] ?? null;
    }

    public static function last(): ?static
    {
        $stmt = Database::getConnection()->query(
            'SELECT * FROM ' . self::quote(static::getTableName()) .
            ' ORDER BY ' . self::quote(static::$primaryKey) . ' DESC LIMIT 1'
        );
        $row = $stmt->fetch();
        return $row ? static::hydrate($row) : null;
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public function save(): bool
    {
        $db    = Database::getConnection();
        $table = self::quote(static::getTableName());
        $key   = static::$primaryKey;
        $id    = $this->attributes[$key] ?? null;

        // UPDATE : uniquement les champs modifiés
        if ($id !== null && $id !== '' && $id !== 0) {
            $changes = [];
            foreach ($this->attributes as $field => $value) {
                if ($field === $key) {
                    continue;
                }
                if (!array_key_exists($field, $this->original) || $this->original[$field] !== $value) {
                    $changes[$field] = $value;
                }
            }
            if (!$changes) {
                return true;
            }

            $sets   = [];
            $params = [];
            $i      = 0;
            foreach ($changes as $field => $value) {
                $sets[]           = self::quote((string) $field) . " = :c{$i}";
                $params["c{$i}"]  = $value;
                $i++;
            }
            $params['pk'] = $id;

            $ok = $db->prepare("UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE ' . self::quote($key) . ' = :pk')
                ->execute($params);
            if ($ok) {
                $this->original = $this->attributes;
            }
            return $ok;
        }

        // INSERT
        $fields       = array_keys($this->attributes);
        $columns      = implode(', ', array_map(static fn($f) => self::quote((string) $f), $fields));
        $placeholders = implode(', ', array_map(static fn($f) => ':' . $f, $fields));

        $ok = $db->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})")->execute($this->attributes);
        if ($ok) {
            $this->attributes[$key] = (int) $db->lastInsertId();
            $this->original         = $this->attributes;
        }
        return $ok;
    }

    public function delete(): bool
    {
        $key = static::$primaryKey;
        if (!isset($this->attributes[$key])) {
            return false;
        }
        return Database::getConnection()
            ->prepare('DELETE FROM ' . self::quote(static::getTableName()) . ' WHERE ' . self::quote($key) . ' = :id')
            ->execute(['id' => $this->attributes[$key]]);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
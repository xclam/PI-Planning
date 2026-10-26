<?php

declare(strict_types=1);

namespace DemoSprint;

final class Ticket
{
    /** @param array<string,string> $fields */
    public function __construct(
        public readonly string $key,
        public readonly string $url,
        public readonly string $type,
        public readonly string $status,
        public readonly bool $isDone,
        public readonly string $assignee,          // dev retenu pour la démo
        public readonly string $role,              // rôle du dev retenu
        public readonly bool $assigneeInferred,    // true = déduit de l'historique (ticket chez PO/Scrum)
        public readonly string $title,
        public readonly ?string $si,
        public readonly bool $isNew,
        public readonly string $transport,         // KAFKA | FILE | N/C
        public readonly array $fields,
    ) {
    }

    public function field(string $id): string
    {
        return $this->fields[$id] ?? '';
    }

    /** @return string[] */
    public function missingFields(): array
    {
        $missing = [];
        foreach (['objectif' => 'Objectif', 'path' => 'PATH', 'hive' => 'HIVE'] as $id => $label) {
            if ($this->field($id) === '') {
                $missing[] = $label;
            }
        }
        return $missing;
    }
}
<?php

declare(strict_types=1);

namespace DemoSprint;

final class Person
{
    public function __construct(
        public readonly string $login,
        public readonly string $name,
        public readonly Role $role,
    ) {
    }
}

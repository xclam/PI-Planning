<?php

declare(strict_types=1);

namespace DemoSprint;

enum Role: string
{
    case Dev   = 'Dev';
    case Po    = 'PO';
    case Scrum = 'Scrum';

    public function isDev(): bool
    {
        return $this === self::Dev;
    }
}

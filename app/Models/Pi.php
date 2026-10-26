<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

class Pi extends Model
{
    protected static ?string $table = 'pis';

    public const STATUS_LABELS = [
        'upcoming' => 'À venir',
        'current'  => 'En cours',
        'past'     => 'Terminé',
    ];

    public function status(): string
    {
        $today = date('Y-m-d');
        $start = substr((string) $this->start_date, 0, 10);
        $end   = substr((string) $this->end_date, 0, 10);

        if ($today < $start) {
            return 'upcoming';
        }
        return $today > $end ? 'past' : 'current';
    }
}
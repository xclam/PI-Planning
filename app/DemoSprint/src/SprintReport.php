<?php

declare(strict_types=1);

namespace DemoSprint;

final class SprintReport
{
    /**
     * @param Ticket[] $tickets  tickets à présenter (réalisés par un dev)
     * @param array<int,array{key:string,title:string,holder:string,reason:string}> $excluded
     * @param array<string,mixed> $stats
     * @param array<int,array{key:string,title:string,type:string,status:string,who:string}>|null $added   null = indéterminable
     * @param array<int,array{key:string,title:string,type:string,status:string,who:string}>|null $removed null = indéterminable
     */
    public function __construct(
        public readonly array $sprint,
        public readonly array $tickets,
        public readonly array $excluded,
        public readonly array $stats,
        public readonly ?array $added = null,
        public readonly ?array $removed = null,
    ) {
    }
}
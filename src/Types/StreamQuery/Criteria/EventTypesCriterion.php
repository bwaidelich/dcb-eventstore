<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery\Criteria;

use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\StreamQuery\Criterion;
use Wwwision\DCBEventStore\Types\StreamQuery\CriterionHash;

final class EventTypesCriterion implements Criterion
{
    private readonly CriterionHash $hash;

    public function __construct(
        public readonly EventTypes $eventTypes,
    ) {
        $this->hash = CriterionHash::fromParts(
            substr(substr(self::class, 0, -9), strrpos(self::class, '\\') + 1),
            implode(',', $this->eventTypes->toStringArray()),
        );
    }

    public function hash(): CriterionHash
    {
        return $this->hash;
    }
}

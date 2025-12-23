<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\AppendCondition;

use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\SequencedEvent\SequencePosition;

/**
 * Condition for {@see EventStore::append()}
 */
final class AppendCondition
{
    private function __construct(
        public readonly Query $failIfEventsMatch,
        public readonly SequencePosition|null $after,
    ) {}

    public static function create(
        Query $failIfEventsMatch,
        SequencePosition|int|null $after = null,
    ): self {
        if (is_int($after)) {
            $after = SequencePosition::fromInteger($after);
        }
        return new self($failIfEventsMatch, $after);
    }
}

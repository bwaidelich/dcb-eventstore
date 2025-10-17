<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\AppendCondition;

use Wwwision\DCBEventStore\Event\SequencePosition;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Query\Query;

/**
 * Condition for {@see EventStore::append()}
 */
final class AppendCondition
{
    public function __construct(
        public readonly Query $failIfEventsMatch,
        public readonly SequencePosition|null $after,
    ) {}
}

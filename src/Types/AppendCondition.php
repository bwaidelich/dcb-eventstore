<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;

/**
 * Condition for {@see EventStore::append()}
 */
final readonly class AppendCondition
{
    public function __construct(
        public StreamQuery $query,
        public ExpectedHighestSequenceNumber $expectedHighestSequenceNumber,
    ) {
    }

    public static function noConstraints(): self
    {
        return new self(StreamQuery::wildcard(), ExpectedHighestSequenceNumber::any());
    }
}

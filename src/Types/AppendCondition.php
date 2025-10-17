<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;

/**
 * Condition for {@see EventStore::append()}
 */
final class AppendCondition
{
    public function __construct(
        public readonly StreamQuery $query,
        public readonly ExpectedHighestSequenceNumber $expectedHighestSequenceNumber,
    ) {}

    public static function noConstraints(): self
    {
        return new self(StreamQuery::wildcard(), ExpectedHighestSequenceNumber::any());
    }
}

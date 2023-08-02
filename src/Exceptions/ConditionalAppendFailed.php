<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Exceptions;

use RuntimeException;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Types\EventId;
use Wwwision\DCBEventStore\Types\ExpectedHighestSequenceNumber;
use Wwwision\DCBEventStore\Types\SequenceNumber;

/**
 * An exception that is thrown when a {@see EventStore::conditionalAppend()} call has failed
 */
final class ConditionalAppendFailed extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }
    public static function becauseNoEventMatchedTheQuery(ExpectedHighestSequenceNumber $expectedHighestSequenceNumber): self
    {
        return new self("Expected highest sequence number \"$expectedHighestSequenceNumber\" but no events in the event store match the specified query");
    }

    public static function becauseNoEventWhereExpected(): self
    {
        return new self('The event store contained events matching the specified query but none were expected');
    }

    public static function becauseHighestExpectedSequenceNumberDoesNotMatch(ExpectedHighestSequenceNumber $expectedHighestSequenceNumber, SequenceNumber $actualSequenceNumber): self
    {
        return new self("Expected highest sequence number \"$expectedHighestSequenceNumber\" does not match the actual id of \"$actualSequenceNumber->value\"");
    }
}

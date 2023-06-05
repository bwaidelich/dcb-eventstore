<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Exception;

use RuntimeException;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Model\EventId;

/**
 * An exception that is thrown when a {@see EventStore::conditionalAppend()} call has failed
 */
final class ConditionalAppendFailed extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }
    public static function becauseNoEventMatchedTheQuery(): self
    {
        return new self('No events in the event store match the specified query');
    }

    public static function becauseNoEventWhereExpected(): self
    {
        return new self('The event store contained events matching the specified query but none were expected');
    }

    public static function becauseEventIdsDontMatch(EventId $expectedId, EventId $actualId): self
    {
        return new self("Expected event id \"$expectedId->value\" does not match the actual id of \"$actualId->value\"");
    }
}

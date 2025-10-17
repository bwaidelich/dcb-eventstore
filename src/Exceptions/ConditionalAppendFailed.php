<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Exceptions;

use RuntimeException;
use Wwwision\DCBEventStore\Event\SequencePosition;
use Wwwision\DCBEventStore\EventStore;

/**
 * An exception that is thrown when a {@see EventStore::conditionalAppend()} call has failed
 */
final class ConditionalAppendFailed extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function becauseMatchingEventsExist(): self
    {
        return new self('The event store contained events matching the specified query but none were expected');
    }

    public static function becauseMatchingEventsExistAfterSequencePosition(SequencePosition $sequencePosition): self
    {
        return new self("The event store contained events matching the specified query after the highest expected sequence position of $sequencePosition->value");
    }
}

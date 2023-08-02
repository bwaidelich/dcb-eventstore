<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;

/**
 * Contract for the Events Store adapter
 */
interface EventStore
{
    /**
     * Returns an event stream that contains events matching the specified {@see StreamQuery} in the order they occurred
     *
     * @param StreamQuery $query The StreamQuery filter every event has to match
     * @param SequenceNumber|null $from If specified, only events with the given {@see SequenceNumber} or a higher one will be returned
     */
    public function read(StreamQuery $query, ?SequenceNumber $from = null): EventStream;

    /**
     * Returns an event stream that contains events matching the specified {@see StreamQuery} in descending order
     *
     * @param StreamQuery $query The StreamQuery filter every event has to match
     * @param SequenceNumber|null $from If specified, only events with the given {@see SequenceNumber} or a lower one will be returned
     */
    public function readBackwards(StreamQuery $query, ?SequenceNumber $from = null): EventStream;

    /**
     * Commits the specified $events if the specified {@see AppendCondition} is satisfied
     *
     * NOTE: This is an atomic operation, so either _all_ events will be committed or _none_
     *
     * @param Events $events The events to append to the event stream
     * @param AppendCondition $condition The condition that has to be met
     * @throws ConditionalAppendFailed If specified $query and $expectedLastEventId don't match
     */
    public function append(Events $events, AppendCondition $condition): void;
}

<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\ReadOptions;
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
     * @param ReadOptions|null $options optional configuration for this interaction ({@see ReadOptions})
     */
    public function read(StreamQuery $query, ?ReadOptions $options = null): EventStream;

    /**
     * Returns an event stream that contains all events
     *
     * @param ReadOptions|null $options optional configuration for this interaction ({@see ReadOptions})
     */
    public function readAll(?ReadOptions $options = null): EventStream;

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

<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\Exception\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Model\Events;
use Wwwision\DCBEventStore\Model\ExpectedLastEventId;
use Wwwision\DCBEventStore\Model\StreamQuery;

/**
 * Contract for the Event Store adapter
 */
interface EventStore
{
    /**
     * Some adapters require setup (e.g. to create required database tables and/or establish/check connection)
     */
    public function setup(): void;

    /**
     * Returns an event stream that contains all events in the order they occurred
     */
    public function streamAll(): EventStream;

    /**
     * Returns an event stream that contains events matching the specified {@see StreamQuery} in the order they occurred
     */
    public function stream(StreamQuery $query): EventStream;

    /**
     * Commits the specified $events without checking any constraints
     */
    public function append(Events $events): void;

    /**
     * Commits the specified $events by checking if the specified $query and $expectedLastEventId match
     *
     * *NOTE:* If the $lastEventId is specified, the last event matching the given $query has to match.
     * If {@see $expectedLastEventId->isNone()}, _no_ event must match the specified $query
     *
     * @param ExpectedLastEventId $expectedLastEventId If not NONE, the last event matching the given $query has to match. Otherwise, _no_ event must match the specified $query
     * @throws ConditionalAppendFailed If specified $query and $expectedLastEventId don't match
     */
    public function conditionalAppend(Events $events, StreamQuery $query, ExpectedLastEventId $expectedLastEventId): void;
}

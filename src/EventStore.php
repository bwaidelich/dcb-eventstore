<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\Exception\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Model\EventId;
use Wwwision\DCBEventStore\Model\Events;
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
     * Returns an event stream matching the specified {@see StreamQuery}
     */
    public function stream(StreamQuery $query): EventStream;

    /**
     * Commits the specified $events without conditions
     * @see conditionalAppend()
     */
    public function append(Events $events): void;

    /**
     * Commits the specified $events by checking if the specified $query and $lastEventId match
     *
     * *NOTE:* If the $lastEventId is specified, the last event matching the given $query has to match.
     * If $lastEventId is NULL, _no_ event must match the specified $query
     *
     * @param ?EventId $lastEventId If specified, the last event matching the given $query has to match. If NULL, _no_ event must match the specified $query
     * @throws ConditionalAppendFailed If specified $quer and $lastEventId don't match
     */
    public function conditionalAppend(Events $events, StreamQuery $query, ?EventId $lastEventId): void;
}

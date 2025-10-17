<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\AppendCondition\AppendCondition;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\Events;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Query\Query;

/**
 * Contract for the Events Store adapter
 */
interface EventStore
{
    /**
     * Returns an event stream that contains events matching the specified {@see Query} in the order they occurred
     *
     * @param Query $query The StreamQuery filter every event has to match
     * @param ReadOptions|null $options optional configuration for this interaction ({@see ReadOptions})
     */
    public function read(Query $query, ReadOptions|null $options = null): SequencedEvents;

    /**
     * Commits the specified $events if the specified {@see AppendCondition} is satisfied
     *
     * NOTE: This is an atomic operation, so either _all_ events will be committed or _none_
     *
     * @param Events|Event $events The events (or a single event) to append to the event stream
     * @param AppendCondition|null $condition The condition that has to be met. If no $condition is specified, events are emitted without any constraint checks
     * @throws ConditionalAppendFailed If specified $condition is violated
     */
    public function append(Events|Event $events, AppendCondition|null $condition = null): void;
}

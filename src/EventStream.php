<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use IteratorAggregate;
use Traversable;
use Wwwision\DCBEventStore\Helper\BatchEventStream;
use Wwwision\DCBEventStore\Model\EventEnvelope;
use Wwwision\DCBEventStore\Model\SequenceNumber;

/**
 * Contract for an event stream returned by {@see EventStore::stream()}
 *
 * @extends IteratorAggregate<EventEnvelope>
 */
interface EventStream extends IteratorAggregate
{
    /**
     * Limits the stream to events with the specified $sequenceNumber or a higher one
     *
     * This method can be used to batch-process events {@see BatchEventStream}
     */
    public function withMinimumSequenceNumber(SequenceNumber $sequenceNumber): self;

    /**
     * Limits the stream to the specified amount of events
     *
     * This method can be used to batch-process events {@see BatchEventStream}
     */
    public function limit(int $limit): self;

    /**
     * Returns the last event envelope of that stream, or NULL if the stream is empty
     */
    public function last(): ?EventEnvelope;

    /**
     * @return Traversable<EventEnvelope>
     */
    public function getIterator(): Traversable;
}

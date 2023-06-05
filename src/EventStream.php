<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use IteratorAggregate;
use Traversable;
use Wwwision\DCBEventStore\Model\EventEnvelope;
use Wwwision\DCBEventStore\Model\SequenceNumber;

/**
 * @extends IteratorAggregate<EventEnvelope>
 */
interface EventStream extends IteratorAggregate
{
    public function withMinimumSequenceNumber(SequenceNumber $sequenceNumber): self;

    public function limit(int $limit): self;

    public function last(): ?EventEnvelope;

    /**
     * @return Traversable<EventEnvelope>
     */
    public function getIterator(): Traversable;
}

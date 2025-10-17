<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use IteratorAggregate;
use Traversable;
use Wwwision\DCBEventStore\Types\EventEnvelope;

/**
 * Contract for an event stream returned by {@see EventStore::read()}
 *
 * @extends IteratorAggregate<EventEnvelope>
 */
interface EventStream extends IteratorAggregate
{
    /**
     * @return Traversable<EventEnvelope>
     */
    public function getIterator(): Traversable;

    public function first(): EventEnvelope|null;
}

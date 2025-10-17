<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use IteratorAggregate;
use Traversable;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvent;

/**
 * Contract for an event stream returned by {@see EventStore::read()}
 *
 * @extends IteratorAggregate<SequencedEvent>
 */
interface SequencedEvents extends IteratorAggregate
{
    /**
     * @return Traversable<SequencedEvent>
     */
    public function getIterator(): Traversable;

    public function first(): SequencedEvent|null;
}

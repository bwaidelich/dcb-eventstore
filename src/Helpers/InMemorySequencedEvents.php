<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helpers;

use Traversable;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvent;
use Wwwision\DCBEventStore\SequencedEvents;

/**
 * An in-memory implementation of the {@see SequencedEvents} interface that mostly serves testing or debugging purposes
 *
 * Usage:
 * $eventStream = InMemorySequencedEvents::create($event1 ,$event2);
 */
final class InMemorySequencedEvents implements SequencedEvents
{
    /**
     * @param SequencedEvent[] $sequencedEvents
     */
    private function __construct(
        private readonly array $sequencedEvents,
    ) {}

    public static function create(SequencedEvent ...$sequencedEvents): self
    {
        return new self($sequencedEvents);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->sequencedEvents as $sequencedEvent) {
            yield $sequencedEvent;
        }
    }

    public function first(): SequencedEvent|null
    {
        return $this->sequencedEvents[array_key_first($this->sequencedEvents)] ?? null;
    }
}

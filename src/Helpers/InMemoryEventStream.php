<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helpers;

use Traversable;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvent;

/**
 * An in-memory implementation of the {@see EventStream} interface that mostly serves testing or debugging purposes
 *
 * Usage:
 * $eventStream = InMemoryEventStream::create($event1 ,$event2);
 */
final class InMemoryEventStream implements EventStream
{
    /**
     * @param SequencedEvent[] $eventEnvelopes
     */
    private function __construct(
        private readonly array $eventEnvelopes,
    ) {}

    public static function create(SequencedEvent ...$events): self
    {
        return new self($events);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->eventEnvelopes as $eventEnvelope) {
            yield $eventEnvelope;
        }
    }

    public function first(): SequencedEvent|null
    {
        return $this->eventEnvelopes[array_key_first($this->eventEnvelopes)] ?? null;
    }
}

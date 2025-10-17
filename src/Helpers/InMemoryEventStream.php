<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helpers;

use Traversable;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Types\EventEnvelope;

/**
 * An in-memory implementation of the {@see EventStream} interface that mostly serves testing or debugging purposes
 *
 * Usage:
 * $eventStream = InMemoryEventStream::create($event1 ,$event2);
 */
final class InMemoryEventStream implements EventStream
{
    /**
     * @param EventEnvelope[] $eventEnvelopes
     */
    private function __construct(
        private readonly array $eventEnvelopes,
    ) {}

    public static function create(EventEnvelope ...$events): self
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

    public function first(): EventEnvelope|null
    {
        return $this->eventEnvelopes[array_key_first($this->eventEnvelopes)] ?? null;
    }
}

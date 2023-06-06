<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helper;

use Traversable;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Model\EventEnvelope;
use Wwwision\DCBEventStore\Model\SequenceNumber;

use function array_key_last;

/**
 * An in-memory implementation of the {@see EventStream} interface that mostly serves testing or debugging purposes
 *
 * Usage:
 * $eventStream = InMemoryEventStream::create($event1 ,$event2);
 */
final readonly class InMemoryEventStream implements EventStream
{
    /**
     * @param EventEnvelope[] $events
     */
    private function __construct(
        private array $events,
        private ?SequenceNumber $minimumSequenceNumber,
        private ?int $limit,
    ) {
    }

    public static function create(EventEnvelope ...$events): self
    {
        return new self($events, null, null);
    }

    public static function empty(): self
    {
        return new self([], null, null);
    }

    public function withMinimumSequenceNumber(SequenceNumber $sequenceNumber): self
    {
        if ($this->minimumSequenceNumber !== null && $sequenceNumber->equals($this->minimumSequenceNumber)) {
            return $this;
        }
        return new self($this->events, $sequenceNumber, $this->limit);
    }

    public function limit(int $limit): self
    {
        if ($limit === $this->limit) {
            return $this;
        }
        return new self($this->events, $this->minimumSequenceNumber, $limit);
    }

    public function last(): ?EventEnvelope
    {
        if ($this->events === []) {
            return null;
        }
        return $this->events[array_key_last($this->events)];
    }

    public function getIterator(): Traversable
    {
        $iteration = 0;
        foreach ($this->events as $eventEnvelope) {
            if ($this->minimumSequenceNumber !== null && $eventEnvelope->sequenceNumber->value < $this->minimumSequenceNumber->value) {
                continue;
            }
            yield $eventEnvelope;
            $iteration++;
            if ($this->limit !== null && $iteration >= $this->limit) {
                return;
            }
        }
    }
}

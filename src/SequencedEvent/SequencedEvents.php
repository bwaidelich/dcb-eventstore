<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\SequencedEvent;

use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

use function array_map;

/**
 * A type-safe set of {@see SequencedEvents} instances
 *
 * @implements IteratorAggregate<SequencedEvent>
 */
final class SequencedEvents implements IteratorAggregate, JsonSerializable, Countable
{
    /**
     * @var array<int, SequencedEvent>
     */
    private readonly array $eventEnvelopes;

    private function __construct(SequencedEvent ...$eventEnvelopes)
    {
        $this->eventEnvelopes = array_values($eventEnvelopes);
    }

    public static function single(SequencedEvent $eventEnvelope): self
    {
        return new self($eventEnvelope);
    }

    /**
     * @param SequencedEvent[] $eventEnvelopes
     */
    public static function fromArray(array $eventEnvelopes): self
    {
        return new self(...$eventEnvelopes);
    }

    public static function none(): self
    {
        return new self();
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->eventEnvelopes);
    }

    public function at(int $index): SequencedEvent
    {
        if (!array_key_exists($index, $this->eventEnvelopes)) {
            throw new \InvalidArgumentException(sprintf('no EventEnvelope at index %d', $index), 1719995162);
        }
        return $this->eventEnvelopes[$index];
    }

    /**
     * @param Closure(SequencedEvent $event): mixed $callback
     * @return array<int, mixed>
     */
    public function map(Closure $callback): array
    {
        return array_map($callback, $this->eventEnvelopes);
    }

    public function filter(Closure $callback): self
    {
        return self::fromArray(array_filter($this->eventEnvelopes, $callback));
    }

    public function append(SequencedEvent|self $eventEnvelopes): self
    {
        if ($eventEnvelopes instanceof SequencedEvent) {
            $eventEnvelopes = self::fromArray([$eventEnvelopes]);
        }
        return self::fromArray([...$this->eventEnvelopes, ...$eventEnvelopes]);
    }

    public function count(): int
    {
        return count($this->eventEnvelopes);
    }

    /**
     * @return SequencedEvent[]
     */
    public function jsonSerialize(): array
    {
        return $this->eventEnvelopes;
    }
}

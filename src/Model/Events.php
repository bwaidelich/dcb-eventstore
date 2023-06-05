<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

use function array_map;

/**
 * @implements IteratorAggregate<Event>
 */
final readonly class Events implements IteratorAggregate, JsonSerializable
{
    /**
     * @var Event[]
     */
    private array $events;

    private function __construct(Event ...$events)
    {
        $this->events = $events;
    }

    public static function single(EventId $id, EventType $type, EventData $data, DomainIds $domainIds): self
    {
        return new self(new Event($id, $type, $data, $domainIds));
    }

    /**
     * @param Event[] $events
     */
    public static function fromArray(array $events): self
    {
        return new self(...$events);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->events);
    }

    /**
     * @return Event[]
     */
    public function jsonSerialize(): array
    {
        return $this->events;
    }

    /**
     * @return array<string, mixed>
     */
    public function map(Closure $callback): array
    {
        return array_map($callback, $this->events);
    }

    public function filter(Closure $callback): self
    {
        return self::fromArray(array_filter($this->events, $callback));
    }

    public function append(Event|self $events): self
    {
        if ($events instanceof Event) {
            $events = self::fromArray([$events]);
        }
        return self::fromArray([...$this->events, ...$events]);
    }
}

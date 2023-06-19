<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * A type-safe set of {@see DomainEvent} instances
 *
 * @implements IteratorAggregate<DomainEvent>
 */
final readonly class DomainEvents implements IteratorAggregate
{
    /**
     * @var DomainEvent[]
     */
    private array $domainEvents;

    private function __construct(DomainEvent ...$domainEvents)
    {
        $this->domainEvents = $domainEvents;
    }

    public static function none(): self
    {
        return new self();
    }

    public static function single(DomainEvent $domainEvent): self
    {
        return new self($domainEvent);
    }

    /**
     * @param DomainEvent[] $domainEvents
     */
    public static function fromArray(array $domainEvents): self
    {
        return new self(...$domainEvents);
    }

    public function isEmpty(): bool
    {
        return $this->domainEvents === [];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->domainEvents);
    }

    public function append(DomainEvent|self $domainEvents): self
    {
        if ($domainEvents instanceof DomainEvent) {
            $domainEvents = self::fromArray([$domainEvents]);
        }
        if ($domainEvents->isEmpty()) {
            return $this;
        }
        return self::fromArray([...$this->domainEvents, ...$domainEvents]);
    }
}

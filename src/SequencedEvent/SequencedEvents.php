<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\SequencedEvent;

use Closure;
use IteratorAggregate;
use Traversable;
use Webmozart\Assert\Assert;

/**
 * The event stream returned by {@see EventStore::read()}
 *
 * @implements IteratorAggregate<SequencedEvent>
 */
final class SequencedEvents implements IteratorAggregate
{
    private SequencedEvent|null $first = null;

    /**
     * @param Closure(): Traversable<SequencedEvent> $generator
     */
    private function __construct(
        private readonly Closure $generator,
    ) {}

    public static function create(callable $generator): self
    {
        return new self($generator(...)); // @phpstan-ignore argument.type
    }

    /**
     * @param array<SequencedEvent> $items
     */
    public static function fromArray(array $items): self
    {
        Assert::allIsInstanceOf($items, SequencedEvent::class);
        return new self(static fn() => yield from $items);
    }

    /**
     * @return Traversable<SequencedEvent>
     */
    public function getIterator(): Traversable
    {
        if ($this->first !== null) {
            yield $this->first;
        }
        foreach (($this->generator)() as $sequencedEvent) {
            Assert::isInstanceOf($sequencedEvent, SequencedEvent::class, 'Invalid instance of %s returned by generator');
            if ($this->first !== null && $sequencedEvent->position->equals($this->first->position)) {
                continue;
            }
            if ($this->first === null) {
                $this->first = $sequencedEvent;
            }
            yield $sequencedEvent;
        }
    }

    public function first(): SequencedEvent|null
    {
        if ($this->first !== null) {
            return $this->first;
        }
        foreach ($this as $sequencedEvent) {
            return $sequencedEvent;
        }
        return null;
    }
}

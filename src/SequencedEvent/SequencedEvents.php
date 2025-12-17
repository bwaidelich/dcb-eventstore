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
final readonly class SequencedEvents implements IteratorAggregate
{
    /**
     * @param Closure(): Traversable<SequencedEvent> $generator
     */
    private function __construct(
        private Closure $generator,
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
        foreach (($this->generator)() as $sequencedEvent) {
            Assert::isInstanceOf($sequencedEvent, SequencedEvent::class, 'Invalid instance of %s returned by generator');
            yield $sequencedEvent;
        }
    }

    public function first(): SequencedEvent|null
    {
        foreach ($this as $sequencedEvent) {
            return $sequencedEvent;
        }
        return null;
    }
}

<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;

use function array_map;

/**
 * A type-safe set of {@see EventTypesAndTagsCriterion} instances
 *
 * @implements IteratorAggregate<EventTypesAndTagsCriterion>
 */
final class Criteria implements IteratorAggregate, JsonSerializable
{
    /**
     * @var EventTypesAndTagsCriterion[]
     */
    private readonly array $criteria;

    private function __construct(EventTypesAndTagsCriterion ...$criteria)
    {
        $this->criteria = $criteria;
    }

    /**
     * @param EventTypesAndTagsCriterion[] $criteria
     */
    public static function fromArray(array $criteria): self
    {
        return new self(...$criteria);
    }

    public static function create(EventTypesAndTagsCriterion ...$criteria): self
    {
        return new self(...$criteria);
    }

    public function with(EventTypesAndTagsCriterion $criterion): self
    {
        return new self(...[...$this->criteria, $criterion]);
    }

    // TODO: Deduplicate
    public function merge(self $other): self
    {
        return new self(...[...$this->criteria, ...$other->criteria]);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->criteria);
    }

    public function matchesEvent(Event $event): bool
    {
        if ($this->isEmpty()) {
            return true;
        }
        foreach ($this->criteria as $criterion) {
            if ($criterion->matchesEvent($event)) {
                return true;
            }
        }
        return false;
    }

    public function isEmpty(): bool
    {
        return $this->criteria === [];
    }

    /**
     * @template TReturn
     * @param Closure(EventTypesAndTagsCriterion $criterion): TReturn $callback
     * @return array<array-key, TReturn>
     */
    public function map(Closure $callback): array
    {
        return array_map($callback, $this->criteria);
    }

    /**
     * @return EventTypesAndTagsCriterion[]
     */
    public function jsonSerialize(): array
    {
        return $this->criteria;
    }
}

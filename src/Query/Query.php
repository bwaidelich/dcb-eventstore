<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Query;

use Closure;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use Webmozart\Assert\Assert;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\EventTypes;
use Wwwision\DCBEventStore\Event\Tags;

/**
 * A Query describing events by their {@see Tags} and/or {@see EventTypes}
 *
 * @implements IteratorAggregate<QueryItem>
 */
final class Query implements IteratorAggregate, JsonSerializable
{
    /**
     * @param array<QueryItem> $items
     */
    private function __construct(
        private readonly array $items,
    ) {}

    public static function fromItems(QueryItem ...$items): self
    {
        Assert::notEmpty($items, 'items must not be empty. Use Query::all() to create a query without any constraints');
        return new self($items);
    }

    public static function all(): self
    {
        return new self([]);
    }

    public function withAddedItems(QueryItem ...$items): self
    {
        return new self([...$this->items, ...$items]);
    }

    public function merge(self $other): self
    {
        if ($this->items === []) {
            return $this;
        }
        if ($other->items === []) {
            return $other;
        }

        // Collect all items and iteratively merge compatible ones
        $merged = [];
        foreach ([...$this->items, ...$other->items] as $item) {
            $foundMatch = false;
            foreach ($merged as $i => $existing) {
                if (self::itemsCanBeMerged($existing, $item)) {
                    $merged[$i] = $existing->merge($item);
                    $foundMatch = true;
                    break;
                }
            }
            if (!$foundMatch) {
                $merged[] = $item;
            }
        }

        return new self($merged);
    }

    public function hasItems(): bool
    {
        return $this->items !== [];
    }

    public function matchesEvent(Event $event): bool
    {
        if ($this->items === []) {
            return true;
        }
        foreach ($this->items as $item) {
            if ($item->matchesEvent($event)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @template T
     * @param Closure(QueryItem): T $callback
     * @return array<T>
     */
    public function map(Closure $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function getIterator(): Traversable
    {
        yield from array_values($this->items);
    }

    /**
     * @return array<QueryItem>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }

    // ----------------------

    private static function itemsCanBeMerged(QueryItem $a, QueryItem $b): bool
    {
        if ($a->onlyLastEvent !== $b->onlyLastEvent) {
            return false;
        }
        if (!self::tagsAreEqual($a->tags, $b->tags)) {
            return false;
        }
        if ($a->onlyLastEvent) {
            // When onlyLastEvent is true, only merge if eventTypes also match
            return self::eventTypesAreEqual($a->eventTypes, $b->eventTypes);
        }
        return true;
    }

    private static function tagsAreEqual(Tags|null $a, Tags|null $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        return $a->equals($b);
    }

    private static function eventTypesAreEqual(EventTypes|null $a, EventTypes|null $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        return $a->equals($b);
    }

}

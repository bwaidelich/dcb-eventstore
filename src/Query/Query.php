<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Query;

use Closure;
use IteratorAggregate;
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
final class Query implements IteratorAggregate
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
        $mergedItems = [];
        $unmergedThis = [];
        $usedOther = [];

        foreach ($this->items as $item) {
            $merged = false;
            foreach ($other->items as $otherItemIndex => $otherItem) {
                if ($item->canBeMerged($otherItem)) {
                    $mergedItems[] = $item->merge($otherItem);
                    $merged = true;
                    $usedOther[$otherItemIndex] = true;
                }
            }
            if (!$merged) {
                $unmergedThis[] = $item;
            }
        }
        // Add items from this query that weren't merged
        array_push($mergedItems, ...$unmergedThis);

        // Add items from other query that weren't merged
        foreach ($other->items as $j => $otherItem) {
            if (!isset($usedOther[$j])) {
                $mergedItems[] = $otherItem;
            }
        }

        return new self($mergedItems);
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
}

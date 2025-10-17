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

    public function hasItems(): bool
    {
        return $this->items !== [];
    }

    public function matchesEvent(Event $event): bool
    {
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

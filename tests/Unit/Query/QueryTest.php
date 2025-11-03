<?php

declare(strict_types=1);

namespace Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;

#[CoversClass(Query::class)]
#[Medium]
final class QueryTest extends TestCase
{
    public function test_all_returns_instance_without_items(): void
    {
        $query = Query::all();
        self::assertSame([], iterator_to_array($query));
    }

    public function test_fromItems_fails_if_invoked_without_items(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Query::fromItems();
    }

    public function test_fromItems_does_not_merge_similar_items(): void
    {
        $item1 = QueryItem::create(eventTypes: 'SomeEventType', tags: 'some-tag');
        $item2 = QueryItem::create(eventTypes: 'SomeEventType', tags: 'some-tag');
        $query = Query::fromItems($item1, $item2);
        self::assertSame([$item1, $item2], iterator_to_array($query));
    }

    public function test_withAddedItems_does_not_merge_similar_items(): void
    {
        $item1 = QueryItem::create(eventTypes: 'SomeEventType', tags: 'some-tag');
        $item2 = QueryItem::create(eventTypes: 'SomeEventType', tags: 'some-tag');
        $item3 = QueryItem::create(eventTypes: 'SomeEventType', tags: 'some-tag');
        $query = Query::fromItems($item1)->withAddedItems($item2, $item3);
        self::assertSame([$item1, $item2, $item3], iterator_to_array($query));
    }

    public function test_hasItems_returns_false_for_wildcard_query(): void
    {
        self::assertFalse(Query::all()->hasItems());
    }

    public function test_hasItems_returns_true_for_query_with_items(): void
    {
        self::assertTrue(Query::fromItems(QueryItem::create(eventTypes: 'SomeEventType'))->hasItems());
    }

    public function test_matchesEvent_returns_true_if_query_has_no_items(): void
    {
        $event = Event::create(type: 'SomeEventType', data: 'data');
        self::assertTrue(Query::all()->matchesEvent($event));
    }

    public function test_matchesEvent_returns_true_if_one_query_item_matches_the_event(): void
    {
        $event = Event::create(type: 'SomeOtherEventType', data: 'data', tags: ['some-other-tag']);
        $query = Query::fromItems(
            QueryItem::create(eventTypes: 'SomeEventType', tags: 'some-tag'),
            QueryItem::create(eventTypes: 'SomeOtherEventType', tags: 'some-other-tag'),
        );
        self::assertTrue($query->matchesEvent($event));
    }

    public function test_matchesEvent_returns_false_if_no_query_item_matches_the_event(): void
    {
        $event = Event::create(type: 'SomeEventType', data: 'data', tags: ['some-other-tag']);
        $query = Query::fromItems(
            QueryItem::create(eventTypes: 'SomeEventType', tags: 'some-tag'),
            QueryItem::create(eventTypes: 'SomeOtherEventType', tags: 'some-other-tag'),
        );
        self::assertFalse($query->matchesEvent($event));
    }

    public function test_map_works_with_wildcard_query(): void
    {
        $result = Query::fromItems(
            QueryItem::create(eventTypes: 'SomeEventType'),
            QueryItem::create(tags: 'someTag'),
        )->map(fn(QueryItem $item) => $item->eventTypes?->toStringArray());
        self::assertSame([['SomeEventType'], null], $result);
    }

    public function test_map_works_with_query_with_items(): void
    {
        $result = Query::all()->map(function () {
            self::fail('Callback should not be invoked');
        });
        self::assertSame([], $result);
    }
}

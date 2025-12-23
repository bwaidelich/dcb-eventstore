<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_merge_returns_wildcard_query_if_source_and_argument_are_wildcard_queries(): void
    {
        $mergedQuery = Query::all()->merge(Query::all());
        self::assertFalse($mergedQuery->hasItems());
    }

    public function test_merge_returns_wildcard_query_if_source_is_wildcard_query(): void
    {
        $mergedQuery = Query::all()->merge(Query::fromItems(QueryItem::create(eventTypes: 'SomeEventType', tags: 'some-tag')));
        self::assertFalse($mergedQuery->hasItems());
    }

    public function test_merge_returns_wildcard_query_if_argument_is_wildcard_query(): void
    {
        $mergedQuery = Query::fromItems(QueryItem::create(eventTypes: 'SomeEventType', tags: 'some-tag'))->merge(Query::all());
        self::assertFalse($mergedQuery->hasItems());
    }

    /**
     * @return iterable<string, array{items1: array<QueryItem>, items2: array<QueryItem>, expectedResult: array<QueryItem>}>
     */
    public static function dataProvider_merge(): iterable
    {
        // Basic cases: merging identical event types
        yield 'identical event types' => ['items1' => [QueryItem::create(eventTypes: 'SomeEventType')], 'items2' => [QueryItem::create(eventTypes: 'SomeEventType')], 'expectedResult' => [QueryItem::create(eventTypes: 'SomeEventType')]];

        // Basic cases: merging different event types with no tags
        yield 'different event types, no tags' => ['items1' => [QueryItem::create(eventTypes: 'SomeEventType')], 'items2' => [QueryItem::create(eventTypes: 'SomeOtherEventType')], 'expectedResult' => [QueryItem::create(eventTypes: ['SomeEventType', 'SomeOtherEventType'])]];

        // Basic cases: different tags cannot be merged
        yield 'different tags' => ['items1' => [QueryItem::create(tags: 'tag1')], 'items2' => [QueryItem::create(tags: 'tag2')], 'expectedResult' => [QueryItem::create(tags: 'tag1'), QueryItem::create(tags: 'tag2')]];

        // Merging with same tags and different event types
        yield 'same tags, different event types' => ['items1' => [QueryItem::create(eventTypes: 'TypeA', tags: 'tag1')], 'items2' => [QueryItem::create(eventTypes: 'TypeB', tags: 'tag1')], 'expectedResult' => [QueryItem::create(eventTypes: ['TypeA', 'TypeB'], tags: 'tag1')]];

        // Merging with same tags and same event types
        yield 'same tags, same event types' => ['items1' => [QueryItem::create(eventTypes: 'TypeA', tags: 'tag1')], 'items2' => [QueryItem::create(eventTypes: 'TypeA', tags: 'tag1')], 'expectedResult' => [QueryItem::create(eventTypes: 'TypeA', tags: 'tag1')]];

        // Multiple items where all can be merged
        yield 'multiple items, all mergeable' => [
            'items1' => [QueryItem::create(eventTypes: 'TypeA'), QueryItem::create(eventTypes: 'TypeB')],
            'items2' => [QueryItem::create(eventTypes: 'TypeC')],
            'expectedResult' => [
                QueryItem::create(eventTypes: ['TypeA', 'TypeC']),
                QueryItem::create(eventTypes: ['TypeB', 'TypeC']),
            ],
        ];

        // Multiple items where some can be merged and some cannot
        yield 'mixed mergeable and non-mergeable' => [
            'items1' => [QueryItem::create(eventTypes: 'TypeA'), QueryItem::create(tags: 'tag1')],
            'items2' => [QueryItem::create(eventTypes: 'TypeB'), QueryItem::create(tags: 'tag2')],
            'expectedResult' => [
                QueryItem::create(eventTypes: ['TypeA', 'TypeB']),
                QueryItem::create(tags: 'tag1'),
                QueryItem::create(tags: 'tag2'),
            ],
        ];

        // Merging with onlyLastEvent flag set to true
        yield 'onlyLastEvent flag true, same tags' => [
            'items1' => [QueryItem::create(eventTypes: 'TypeA', onlyLastEvent: true)],
            'items2' => [QueryItem::create(eventTypes: 'TypeB', onlyLastEvent: true)],
            'expectedResult' => [QueryItem::create(eventTypes: ['TypeA', 'TypeB'], onlyLastEvent: true)],
        ];

        // Cannot merge items with different onlyLastEvent flags
        yield 'different onlyLastEvent flags' => [
            'items1' => [QueryItem::create(eventTypes: 'TypeA', onlyLastEvent: true)],
            'items2' => [QueryItem::create(eventTypes: 'TypeB', onlyLastEvent: false)],
            'expectedResult' => [
                QueryItem::create(eventTypes: 'TypeA', onlyLastEvent: true),
                QueryItem::create(eventTypes: 'TypeB', onlyLastEvent: false),
            ],
        ];

        // Merging where one has eventTypes and the other doesn't (but same tags)
        yield 'one with eventTypes, one without, same tags' => [
            'items1' => [QueryItem::create(eventTypes: 'TypeA', tags: 'tag1')],
            'items2' => [QueryItem::create(tags: 'tag1')],
            'expectedResult' => [QueryItem::create(eventTypes: 'TypeA', tags: 'tag1')],
        ];

        // Multiple items in both queries with various combinations
        yield 'complex scenario with multiple items' => [
            'items1' => [
                QueryItem::create(eventTypes: 'TypeA', tags: 'tag1'),
                QueryItem::create(eventTypes: 'TypeB', tags: 'tag2'),
                QueryItem::create(tags: 'tag3'),
            ],
            'items2' => [
                QueryItem::create(eventTypes: 'TypeC', tags: 'tag1'),
                QueryItem::create(eventTypes: 'TypeD', tags: 'tag4'),
                QueryItem::create(tags: 'tag3'),
            ],
            'expectedResult' => [
                QueryItem::create(eventTypes: ['TypeA', 'TypeC'], tags: 'tag1'),
                QueryItem::create(tags: 'tag3'),
                QueryItem::create(eventTypes: 'TypeB', tags: 'tag2'),
                QueryItem::create(eventTypes: 'TypeD', tags: 'tag4'),
            ],
        ];

        // Merging with multiple event types already present
        yield 'multiple event types already present' => [
            'items1' => [QueryItem::create(eventTypes: ['TypeA', 'TypeB'])],
            'items2' => [QueryItem::create(eventTypes: ['TypeC', 'TypeD'])],
            'expectedResult' => [QueryItem::create(eventTypes: ['TypeA', 'TypeB', 'TypeC', 'TypeD'])],
        ];

        // Three items in each query with same tags
        yield 'three items each, all same tags' => [
            'items1' => [
                QueryItem::create(eventTypes: 'TypeA', tags: 'tag1'),
                QueryItem::create(eventTypes: 'TypeB', tags: 'tag1'),
                QueryItem::create(eventTypes: 'TypeC', tags: 'tag1'),
            ],
            'items2' => [
                QueryItem::create(eventTypes: 'TypeD', tags: 'tag1'),
                QueryItem::create(eventTypes: 'TypeE', tags: 'tag1'),
                QueryItem::create(eventTypes: 'TypeF', tags: 'tag1'),
            ],
            'expectedResult' => [
                QueryItem::create(eventTypes: ['TypeA', 'TypeD'], tags: 'tag1'),
                QueryItem::create(eventTypes: ['TypeA', 'TypeE'], tags: 'tag1'),
                QueryItem::create(eventTypes: ['TypeA', 'TypeF'], tags: 'tag1'),
                QueryItem::create(eventTypes: ['TypeB', 'TypeD'], tags: 'tag1'),
                QueryItem::create(eventTypes: ['TypeB', 'TypeE'], tags: 'tag1'),
                QueryItem::create(eventTypes: ['TypeB', 'TypeF'], tags: 'tag1'),
                QueryItem::create(eventTypes: ['TypeC', 'TypeD'], tags: 'tag1'),
                QueryItem::create(eventTypes: ['TypeC', 'TypeE'], tags: 'tag1'),
                QueryItem::create(eventTypes: ['TypeC', 'TypeF'], tags: 'tag1'),
            ],
        ];
    }

    /**
     * @param array<QueryItem> $items1
     * @param array<QueryItem> $items2
     * @param array<QueryItem> $expectedResult
     */
    #[DataProvider('dataProvider_merge')]
    public function test_merge(array $items1, array $items2, array $expectedResult): void
    {
        $query1 = Query::fromItems(...$items1);
        $query2 = Query::fromItems(...$items2);
        $result = $query1->merge($query2);
        self::assertEquals($expectedResult, iterator_to_array($result));
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

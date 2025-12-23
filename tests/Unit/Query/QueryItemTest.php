<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Query;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\EventData;
use Wwwision\DCBEventStore\Event\EventType;
use Wwwision\DCBEventStore\Event\EventTypes;
use Wwwision\DCBEventStore\Event\Tags;
use Wwwision\DCBEventStore\Query\QueryItem;

#[CoversClass(QueryItem::class)]
#[Medium]
final class QueryItemTest extends TestCase
{
    public function test_create_throws_if_both_eventTypes_and_tags_are_null(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1716131425);
        QueryItem::create(eventTypes: null, tags: null);
    }

    public function test_create_with_string_eventTypes(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'SomeEventType');

        self::assertInstanceOf(EventTypes::class, $queryItem->eventTypes);
        self::assertNull($queryItem->tags);
        self::assertFalse($queryItem->onlyLastEvent);
    }

    public function test_create_with_array_eventTypes(): void
    {
        $queryItem = QueryItem::create(eventTypes: ['Type1', 'Type2']);

        self::assertInstanceOf(EventTypes::class, $queryItem->eventTypes);
        self::assertNull($queryItem->tags);
        self::assertFalse($queryItem->onlyLastEvent);
    }

    public function test_create_with_EventTypes_instance(): void
    {
        $eventTypes = EventTypes::fromStrings('Type1', 'Type2');
        $queryItem = QueryItem::create(eventTypes: $eventTypes);

        self::assertSame($eventTypes, $queryItem->eventTypes);
        self::assertNull($queryItem->tags);
        self::assertFalse($queryItem->onlyLastEvent);
    }

    public function test_create_with_string_tags(): void
    {
        $queryItem = QueryItem::create(tags: 'some-tag');

        self::assertNull($queryItem->eventTypes);
        self::assertInstanceOf(Tags::class, $queryItem->tags);
        self::assertFalse($queryItem->onlyLastEvent);
    }

    public function test_create_with_array_tags(): void
    {
        $queryItem = QueryItem::create(tags: ['tag1', 'tag2']);

        self::assertNull($queryItem->eventTypes);
        self::assertInstanceOf(Tags::class, $queryItem->tags);
        self::assertFalse($queryItem->onlyLastEvent);
    }

    public function test_create_with_Tags_instance(): void
    {
        $tags = Tags::fromArray(['tag1', 'tag2']);
        $queryItem = QueryItem::create(tags: $tags);

        self::assertNull($queryItem->eventTypes);
        self::assertSame($tags, $queryItem->tags);
        self::assertFalse($queryItem->onlyLastEvent);
    }

    public function test_create_with_onlyLastEvent_true(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'SomeType', onlyLastEvent: true);

        self::assertTrue($queryItem->onlyLastEvent);
    }

    public function test_create_with_both_eventTypes_and_tags(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'SomeType', tags: 'some-tag');

        self::assertInstanceOf(EventTypes::class, $queryItem->eventTypes);
        self::assertInstanceOf(Tags::class, $queryItem->tags);
        self::assertFalse($queryItem->onlyLastEvent);
    }

    public function test_with_updates_eventTypes(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'Type1');
        $updated = $queryItem->with(eventTypes: 'Type2');

        self::assertNotSame($queryItem, $updated);
        self::assertInstanceOf(EventTypes::class, $updated->eventTypes);
    }

    public function test_with_updates_tags(): void
    {
        $queryItem = QueryItem::create(tags: 'tag1');
        $updated = $queryItem->with(tags: 'tag2');

        self::assertNotSame($queryItem, $updated);
        self::assertInstanceOf(Tags::class, $updated->tags);
    }

    public function test_with_updates_onlyLastEvent(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'SomeType', onlyLastEvent: false);
        $updated = $queryItem->with(onlyLastEvent: true);

        self::assertNotSame($queryItem, $updated);
        self::assertFalse($queryItem->onlyLastEvent);
        self::assertTrue($updated->onlyLastEvent);
    }

    public function test_with_preserves_unchanged_properties(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'Type1', tags: 'tag1', onlyLastEvent: true);
        $updated = $queryItem->with(eventTypes: 'Type2');

        self::assertSame($queryItem->tags, $updated->tags);
        self::assertTrue($updated->onlyLastEvent);
    }

    public function test_with_updates_eventTypes_with_array(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'Type1');
        $updated = $queryItem->with(eventTypes: ['Type2', 'Type3']);

        self::assertNotSame($queryItem, $updated);
        self::assertInstanceOf(EventTypes::class, $updated->eventTypes);
    }

    public function test_with_updates_tags_with_array(): void
    {
        $queryItem = QueryItem::create(tags: 'tag1');
        $updated = $queryItem->with(tags: ['tag2', 'tag3']);

        self::assertNotSame($queryItem, $updated);
        self::assertInstanceOf(Tags::class, $updated->tags);
    }

    public function test_canBeMerged_returns_true_for_matching_onlyLastEvent_and_tags(): void
    {
        $item1 = QueryItem::create(eventTypes: 'Type1', tags: 'tag1', onlyLastEvent: true);
        $item2 = QueryItem::create(eventTypes: 'Type2', tags: 'tag1', onlyLastEvent: true);

        self::assertTrue($item1->canBeMerged($item2));
    }

    public function test_canBeMerged_returns_false_for_different_onlyLastEvent(): void
    {
        $item1 = QueryItem::create(eventTypes: 'Type1', tags: 'tag1', onlyLastEvent: true);
        $item2 = QueryItem::create(eventTypes: 'Type2', tags: 'tag1', onlyLastEvent: false);

        self::assertFalse($item1->canBeMerged($item2));
    }

    public function test_canBeMerged_returns_false_for_different_tags(): void
    {
        $item1 = QueryItem::create(eventTypes: 'Type1', tags: 'tag1');
        $item2 = QueryItem::create(eventTypes: 'Type2', tags: 'tag2');

        self::assertFalse($item1->canBeMerged($item2));
    }

    public function test_canBeMerged_returns_true_for_both_null_tags(): void
    {
        $item1 = QueryItem::create(eventTypes: 'Type1');
        $item2 = QueryItem::create(eventTypes: 'Type2');

        self::assertTrue($item1->canBeMerged($item2));
    }

    public function test_merge_combines_eventTypes(): void
    {
        $item1 = QueryItem::create(eventTypes: 'Type1', tags: 'tag1');
        $item2 = QueryItem::create(eventTypes: 'Type2', tags: 'tag1');

        $merged = $item1->merge($item2);

        self::assertInstanceOf(EventTypes::class, $merged->eventTypes);
        self::assertTrue($merged->eventTypes->contain(EventType::fromString('Type1')));
        self::assertTrue($merged->eventTypes->contain(EventType::fromString('Type2')));
    }

    public function test_merge_throws_for_different_onlyLastEvent(): void
    {
        $item1 = QueryItem::create(eventTypes: 'Type1', tags: 'tag1', onlyLastEvent: true);
        $item2 = QueryItem::create(eventTypes: 'Type2', tags: 'tag1', onlyLastEvent: false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query items with different values for "onlyLasEvent" flag cannot be merged');
        $item1->merge($item2);
    }

    public function test_merge_throws_for_different_tags(): void
    {
        $item1 = QueryItem::create(eventTypes: 'Type1', tags: 'tag1');
        $item2 = QueryItem::create(eventTypes: 'Type2', tags: 'tag2');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query items with tag mismatch cannot be merged');
        $item1->merge($item2);
    }

    public function test_merge_with_null_eventTypes_in_first_item(): void
    {
        $item1 = QueryItem::create(tags: 'tag1');
        $item2 = QueryItem::create(eventTypes: 'Type2', tags: 'tag1');

        $merged = $item1->merge($item2);

        self::assertSame($item2->eventTypes, $merged->eventTypes);
    }

    public function test_merge_with_null_eventTypes_in_second_item(): void
    {
        $item1 = QueryItem::create(eventTypes: 'Type1', tags: 'tag1');
        $item2 = QueryItem::create(tags: 'tag1');

        $merged = $item1->merge($item2);

        self::assertSame($item1->eventTypes, $merged->eventTypes);
    }

    public function test_matchesEvent_returns_true_for_matching_type_and_tags(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'SomeType', tags: 'tag1');
        $event = Event::create(type: 'SomeType', data: EventData::fromString('{}'), tags: ['tag1', 'tag2']);

        self::assertTrue($queryItem->matchesEvent($event));
    }

    public function test_matchesEvent_returns_false_for_non_matching_type(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'SomeType', tags: 'tag1');
        $event = Event::create(type: 'OtherType', data: EventData::fromString('{}'), tags: 'tag1');

        self::assertFalse($queryItem->matchesEvent($event));
    }

    public function test_matchesEvent_returns_false_for_missing_required_tag(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'SomeType', tags: 'tag1');
        $event = Event::create(type: 'SomeType', data: EventData::fromString('{}'), tags: 'tag2');

        self::assertFalse($queryItem->matchesEvent($event));
    }

    public function test_matchesEvent_returns_true_when_only_tags_specified(): void
    {
        $queryItem = QueryItem::create(tags: 'tag1');
        $event = Event::create(type: 'SomeType', data: EventData::fromString('{}'), tags: 'tag1');

        self::assertTrue($queryItem->matchesEvent($event));
    }

    public function test_matchesEvent_returns_true_when_only_eventTypes_specified(): void
    {
        $queryItem = QueryItem::create(eventTypes: 'SomeType');
        $event = Event::create(type: 'SomeType', data: EventData::fromString('{}'), tags: 'tag1');

        self::assertTrue($queryItem->matchesEvent($event));
    }

    public function test_matchesEvent_returns_true_for_event_with_all_required_tags(): void
    {
        $queryItem = QueryItem::create(tags: ['tag1', 'tag2']);
        $event = Event::create(type: 'SomeType', data: EventData::fromString('{}'), tags: ['tag1', 'tag2', 'tag3']);

        self::assertTrue($queryItem->matchesEvent($event));
    }

    public function test_matchesEvent_returns_false_for_event_missing_one_required_tag(): void
    {
        $queryItem = QueryItem::create(tags: ['tag1', 'tag2']);
        $event = Event::create(type: 'SomeType', data: EventData::fromString('{}'), tags: ['tag1', 'tag3']);

        self::assertFalse($queryItem->matchesEvent($event));
    }
}

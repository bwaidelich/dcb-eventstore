<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Types\StreamQuery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventData;
use Wwwision\DCBEventStore\Types\EventId;
use Wwwision\DCBEventStore\Types\EventMetadata;
use Wwwision\DCBEventStore\Types\EventType;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\TagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBEventStore\Types\Tag;
use Wwwision\DCBEventStore\Types\Tags;

#[CoversClass(StreamQuery::class)]
#[CoversClass(Tags::class)]
#[CoversClass(Tag::class)]
#[CoversClass(EventTypes::class)]
#[CoversClass(Criteria::class)]
#[CoversClass(TagsCriterion::class)]
#[CoversClass(EventTypesCriterion::class)]
#[CoversClass(EventTypesAndTagsCriterion::class)]
final class StreamQueryTest extends TestCase
{

    public static function dataprovider_no_match(): iterable
    {
        $eventType = EventType::fromString('SomeEventType');
        $tags = Tags::fromArray(['foo:bar', 'bar:baz']);
        $event = new Event(EventId::create(), $eventType, EventData::fromString(''), $tags, EventMetadata::none());

        yield 'different tag' => ['query' => StreamQuery::create(Criteria::create(new TagsCriterion(Tags::single('foo', 'not_bar')))), 'event' => $event];
        yield 'different event type' => ['query' => StreamQuery::create(Criteria::create(new EventTypesCriterion(EventTypes::single('SomeOtherEventType')))), 'event' => $event];
        yield 'matching event type, different tag value' => ['query' => StreamQuery::create(Criteria::create(new EventTypesAndTagsCriterion(EventTypes::single('SomeEventType'), Tags::single('foo', 'not_bar')))), 'event' => $event];
        yield 'matching all tags plus additional tags' => ['query' => StreamQuery::create(Criteria::create(new TagsCriterion(Tags::fromArray(['foo:bar', 'bar:baz', 'foos:bars'])))), 'event' => $event];

        yield 'partially matching tags' => ['query' => StreamQuery::create(Criteria::create(new TagsCriterion(Tags::fromArray(['foo:bar', 'bar:not_baz'])))), 'event' => $event];
        yield 'matching tag, different event type' => ['query' => StreamQuery::create(Criteria::create(new EventTypesAndTagsCriterion(EventTypes::single('Event4'), Tags::fromArray(['key2:value1', 'key1:value3'])))), 'event' => new Event(EventId::create(), EventType::fromString('Event3'), EventData::fromString(''), Tags::single('key2', 'value1'), EventMetadata::none())];
    }

    /**
     * @dataProvider dataprovider_no_match
     */
    public function test_matches_false(StreamQuery $query, Event $event): void
    {
        self::assertFalse($query->matches($event));
    }

    public static function dataprovider_matches(): iterable
    {
        $eventType = EventType::fromString('SomeEventType');
        $tags = Tags::fromArray(['foo:bar', 'bar:baz']);
        $event = new Event(EventId::create(), $eventType, EventData::fromString(''), $tags, EventMetadata::none());

        yield 'matching tag type and value' => ['query' => StreamQuery::create(Criteria::create(new TagsCriterion(Tags::single('foo', 'bar')))), 'event' => $event];
        yield 'matching event type' => ['query' => StreamQuery::create(Criteria::create(new EventTypesCriterion(EventTypes::single('SomeEventType')))), 'event' => $event];
        yield 'matching one of two event types' => ['query' => StreamQuery::create(Criteria::create(new EventTypesCriterion(EventTypes::create(EventType::fromString('SomeOtherEventType'), EventType::fromString('SomeEventType'))))), 'event' => $event];
        yield 'matching event type and tag' => ['query' => StreamQuery::create(Criteria::create(new EventTypesAndTagsCriterion(EventTypes::single('SomeEventType'), Tags::single('foo', 'bar')))), 'event' => $event];

    }

    /**
     * @dataProvider dataprovider_matches
     */
    public function test_matches_true(StreamQuery $query, Event $event): void
    {
        self::assertTrue($query->matches($event));
    }
}
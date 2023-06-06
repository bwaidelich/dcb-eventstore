<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Model\DomainIds;
use Wwwision\DCBEventStore\Model\Event;
use Wwwision\DCBEventStore\Model\EventData;
use Wwwision\DCBEventStore\Model\EventId;
use Wwwision\DCBEventStore\Model\EventType;
use Wwwision\DCBEventStore\Model\EventTypes;
use Wwwision\DCBEventStore\Model\StreamQuery;

#[CoversClass(StreamQuery::class)]
final class StreamQueryTest extends TestCase
{

    public static function dataprovider_matches(): iterable
    {
        $eventType1 = EventType::fromString('SomeEventType');
        $domainIds1 = DomainIds::fromArray(['foo' => 'bar', 'bar' => 'baz']);
        $event = new Event(EventId::create(), $eventType1, EventData::fromString(''), $domainIds1);

        yield ['query' => StreamQuery::matchingNone(), 'event' => $event, 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingIds(DomainIds::none()), 'event' => $event, 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingIds(DomainIds::single('foo', 'not_bar')), 'event' => $event, 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingTypes(EventTypes::none()), 'event' => $event, 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingTypes(EventTypes::single('SomeOtherEventType')), 'event' => $event, 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingIdsAndTypes(DomainIds::single('foo', 'bar'), EventTypes::none()), 'event' => $event, 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingIdsAndTypes(DomainIds::single('foo', 'not_bar'), EventTypes::single('SomeEventType')), 'event' => $event, 'expectedResult' => false];

        yield ['query' => StreamQuery::matchingIds(DomainIds::single('foo', 'bar')), 'event' => $event, 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingIds(DomainIds::fromArray(['foo' => 'bar', 'bar' => 'not_baz'])), 'event' => $event, 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingIds(DomainIds::fromArray(['foo' => 'bar', 'bar' => 'baz', 'foos' => 'bars'])), 'event' => $event, 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingTypes(EventTypes::single('SomeEventType')), 'event' => $event, 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingTypes(EventTypes::create(EventType::fromString('SomeOtherEventType'), EventType::fromString('SomeEventType'))), 'event' => $event, 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingIdsAndTypes(DomainIds::single('foo', 'bar'), EventTypes::single('SomeEventType')), 'event' => $event, 'expectedResult' => true];
    }

    /**
     * @dataProvider dataprovider_matches
     */
    public function test_matches(StreamQuery $query, Event $event, bool $expectedResult): void
    {
        if ($expectedResult === true) {
            self::assertTrue($query->matches($event));
        } else {
            self::assertFalse($query->matches($event));
        }
    }

    public static function dataprovider_matchesNone(): iterable
    {
        yield ['query' => StreamQuery::matchingNone(), 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingIds(DomainIds::none()), 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingTypes(EventTypes::none()), 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingIdsAndTypes(DomainIds::none(), EventTypes::single('SomeEventType')), 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingIdsAndTypes(DomainIds::single('foo', 'bar'), EventTypes::none()), 'expectedResult' => true];

        yield ['query' => StreamQuery::matchingIds(DomainIds::single('foo', 'bar')), 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingTypes(EventTypes::single('SomeEventType')), 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingIdsAndTypes(DomainIds::single('foo', 'bar'), EventTypes::single('SomeEventType')), 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingAny(), 'expectedResult' => false];
    }

    /**
     * @dataProvider dataprovider_matchesNone
     */
    public function test_matchesNone(StreamQuery $query, bool $expectedResult): void
    {
        if ($expectedResult === true) {
            self::assertTrue($query->matchesNone());
        } else {
            self::assertFalse($query->matchesNone());
        }
    }
}
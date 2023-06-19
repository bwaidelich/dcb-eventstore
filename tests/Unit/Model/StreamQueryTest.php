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
#[CoversClass(DomainIds::class)]
#[CoversClass(EventTypes::class)]
final class StreamQueryTest extends TestCase
{

    public static function dataprovider_matches(): iterable
    {
        $eventType1 = EventType::fromString('SomeEventType');
        $domainIds1 = DomainIds::fromArray([['foo' => 'bar'], ['bar' => 'baz']]);
        $event = new Event(EventId::create(), $eventType1, EventData::fromString(''), $domainIds1);

        yield ['query' => StreamQuery::matchingIds(DomainIds::single('foo', 'not_bar')), 'event' => $event, 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingTypes(EventTypes::single('SomeOtherEventType')), 'event' => $event, 'expectedResult' => false];
        yield ['query' => StreamQuery::matchingIdsAndTypes(DomainIds::single('foo', 'not_bar'), EventTypes::single('SomeEventType')), 'event' => $event, 'expectedResult' => false];

        yield ['query' => StreamQuery::matchingIds(DomainIds::single('foo', 'bar')), 'event' => $event, 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingIds(DomainIds::fromArray([['foo' => 'bar'], ['bar' => 'not_baz']])), 'event' => $event, 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingIds(DomainIds::fromArray([['foo' => 'bar'], ['bar' => 'baz'], ['foos' => 'bars']])), 'event' => $event, 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingTypes(EventTypes::single('SomeEventType')), 'event' => $event, 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingTypes(EventTypes::create(EventType::fromString('SomeOtherEventType'), EventType::fromString('SomeEventType'))), 'event' => $event, 'expectedResult' => true];
        yield ['query' => StreamQuery::matchingIdsAndTypes(DomainIds::single('foo', 'bar'), EventTypes::single('SomeEventType')), 'event' => $event, 'expectedResult' => true];

        yield ['query' => StreamQuery::matchingIdsAndTypes(DomainIds::fromArray([['key2' => 'value1'], ['key1' => 'value3']]), EventTypes::single('Event4')), 'event' => new Event(EventId::create(), EventType::fromString('Event3'), EventData::fromString(''), DomainIds::single('key2', 'value1')), 'expectedResult' => false];
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
}
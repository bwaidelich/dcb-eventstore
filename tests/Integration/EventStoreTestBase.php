<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration;

use InvalidArgumentException;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Exception\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Model\DomainIds;
use Wwwision\DCBEventStore\Model\Event;
use Wwwision\DCBEventStore\Model\EventData;
use Wwwision\DCBEventStore\Model\EventEnvelope;
use Wwwision\DCBEventStore\Model\EventId;
use Wwwision\DCBEventStore\Model\Events;
use Wwwision\DCBEventStore\Model\EventType;
use Wwwision\DCBEventStore\Model\EventTypes;
use Wwwision\DCBEventStore\Model\ExpectedLastEventId;
use Wwwision\DCBEventStore\Model\SequenceNumber;
use Wwwision\DCBEventStore\Model\StreamQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Tests\Unit\EventEnvelopeShape;
use Wwwision\DCBEventStore\Tests\Unit\EventShape;
use function array_map;
use function in_array;
use function range;

/**
 * @phpstan-type EventShape array{id?: string, type?: string, data?: string, domainIds?: array<array<string, string>>}
 * @phpstan-type EventEnvelopeShape array{id?: string, type?: string, data?: string, domainIds?: array<array<string, string>>, sequenceNumber?: int}
 */
#[CoversClass(DomainIds::class)]
#[CoversClass(EventData::class)]
#[CoversClass(ConditionalAppendFailed::class)]
#[CoversClass(EventEnvelope::class)]
#[CoversClass(EventId::class)]
#[CoversClass(EventType::class)]
#[CoversClass(EventTypes::class)]
#[CoversClass(Event::class)]
#[CoversClass(Events::class)]
#[CoversClass(SequenceNumber::class)]
#[CoversClass(StreamQuery::class)]
abstract class EventStoreTestBase extends TestCase
{

    private ?EventStore $eventStore = null;

    abstract protected function createEventStore(): EventStore;

    public function test_streamAll_returns_an_empty_stream_if_no_events_were_published(): void
    {
        self::assertEventStream($this->getEventStore()->streamAll(), []);
    }

    public function test_streamAll_returns_all_events(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->streamAll(), [
            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'domainIds' => [['baz' => 'foos'], ['foo' => 'bar']], 'sequenceNumber' => 1],
            ['id' => 'id-b', 'data' => 'b', 'type' => 'SomeOtherEventType', 'domainIds' => [['foo' => 'bar']],'sequenceNumber' => 2],
            ['id' => 'id-c', 'data' => 'c', 'type' => 'SomeEventType', 'domainIds' => [['foo' => 'bar']],'sequenceNumber' => 3],
            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'domainIds' => [['baz' => 'foos'], ['foo' => 'bar']],'sequenceNumber' => 4],
            ['id' => 'id-e', 'data' => 'e', 'type' => 'SomeEventType', 'domainIds' => [['baz' => 'foos'], ['foo' => 'bar']],'sequenceNumber' => 5],
            ['id' => 'id-f', 'data' => 'f', 'type' => 'SomeOtherEventType', 'domainIds' => [['baz' => 'foos'], ['foo' => 'bar']],'sequenceNumber' => 6],
        ]);
    }

    public function test_stream_allows_filtering_of_events_by_domain_id(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->stream(StreamQuery::matchingIds(DomainIds::single('baz', 'foos'),)), [
            ['data' => 'a'],
            ['data' => 'd'],
            ['data' => 'e'],
            ['data' => 'f'],
        ]);
    }

    public function test_stream_allows_filtering_of_events_by_domain_ids(): void
    {
        $this->appendEvents([
            ['id' => 'a', 'domainIds' => [['foo' => 'bar']]],
            ['id' => 'b', 'domainIds' => [['foo' => 'bar'], ['baz' => 'foos']]],
            ['id' => 'c', 'domainIds' => [['baz' => 'foos'], ['foo' => 'bar']]],
            ['id' => 'd', 'domainIds' => [['baz' => 'foos']]],
            ['id' => 'e', 'domainIds' => [['baz' => 'foosnot']]],
            ['id' => 'f', 'domainIds' => [['foo' => 'bar'], ['baz' => 'notfoos']]],
            ['id' => 'g', 'domainIds' => [['baz' => 'foos'], ['foo' => 'bar'], ['foos' => 'baz']]],
            ['id' => 'h', 'domainIds' => [['baz' => 'foosn'], ['foo' => 'notbar'], ['foos' => 'bar']]],
        ]);
        self::assertEventStream($this->getEventStore()->stream(StreamQuery::matchingIds(DomainIds::fromArray([['foo' => 'bar'], ['baz' => 'foos']]),)), [
            ['id' => 'a'],
            ['id' => 'b'],
            ['id' => 'c'],
            ['id' => 'd'],
            ['id' => 'f'],
            ['id' => 'g'],
        ]);
    }

    public function test_stream_allows_filtering_of_events_by_event_types(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->stream(StreamQuery::matchingTypes(EventTypes::single('SomeEventType'))), [
            ['data' => 'a'],
            ['data' => 'c'],
            ['data' => 'e'],
        ]);
    }

    public function test_stream_allows_filtering_of_events_by_domain_ids_and_event_types(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->stream(StreamQuery::matchingIdsAndTypes(DomainIds::single('baz', 'foos'), EventTypes::single('SomeEventType'))), [
            ['data' => 'a'],
            ['data' => 'e'],
        ]);
    }

    public function test_stream_allows_fetching_no_events(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->stream(StreamQuery::matchingIds(DomainIds::single('non-existing', 'id'))), []);
    }

    public function test_conditionalAppend_appends_event_if_expectedLastEventId_matches(): void
    {
        $this->appendDummyEvents();

        $query = StreamQuery::matchingIdsAndTypes(DomainIds::single('baz', 'foos'), EventTypes::single('SomeEventType'));
        $stream = $this->getEventStore()->stream($query);
        $lastEventId = $stream->last()->event->id;
        $this->conditionalAppendEvent(['type' => 'SomeEventType', 'data' => 'new event', 'domainIds' => [['baz' => 'foos']]], $query, ExpectedLastEventId::fromEventId($lastEventId));

        self::assertEventStream($this->getEventStore()->stream(StreamQuery::matchingIdsAndTypes(DomainIds::single('baz', 'foos'), EventTypes::single('SomeEventType'))), [
            ['data' => 'a'],
            ['data' => 'e'],
            ['data' => 'new event'],
        ]);
    }

    public function test_conditionalAppend_fails_if_new_events_match_the_specified_query(): void
    {
        $this->appendDummyEvents();

        $query = StreamQuery::matchingIdsAndTypes(DomainIds::single('baz', 'foos'), EventTypes::single('SomeEventType'));
        $stream = $this->getEventStore()->stream($query);
        $lastEventId = $stream->last()->event->id;

        $this->appendEvent(['type' => 'SomeEventType', 'domainIds' => [['baz' => 'foos']]]);

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedLastEventId::fromEventId($lastEventId));
    }

    public function test_conditionalAppend_fails_if_no_last_event_id_was_expected_but_query_matches_events(): void
    {
        $this->appendDummyEvents();

        $query = StreamQuery::matchingIdsAndTypes(DomainIds::single('baz', 'foos'), EventTypes::single('SomeEventType'));

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedLastEventId::none());
    }

    public function test_conditionalAppend_fails_if_last_event_id_was_expected_but_query_matches_no_events(): void
    {
        $query = StreamQuery::matchingIdsAndTypes(DomainIds::single('baz', 'foos'), EventTypes::single('SomeEventTypeThatDidNotOccur'));

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedLastEventId::fromString('some-expected-id'));
    }

    // --- Helpers ---

    final protected function appendDummyEvents(): void
    {
        $this->appendEvents(array_map(static fn ($char) => [
            'id' => 'id-' . $char,
            'data' => $char,
            'type' => in_array($char, ['a', 'c', 'e'], true) ? 'SomeEventType' : 'SomeOtherEventType',
            'domainIds' => in_array($char, ['b', 'c'], true) ? [['foo' => 'bar']] : [['foo' => 'bar'], ['baz' => 'foos']],
        ], range('a', 'f')));
    }

    /**
     * @phpstan-param EventShape $event
     */
    final protected function appendEvent(array $event): void
    {
        $this->appendEvents([$event]);
    }

    /**
     * @phpstan-param EventShape $event
     */
    final protected function appendEvents(array $events): void
    {
        $this->getEventStore()->append(Events::fromArray(array_map(self::arrayToEvent(...), $events)));
    }

    /**
     * @phpstan-param EventShape $event
     */
    final protected function conditionalAppendEvents(array $events, StreamQuery $query, ExpectedLastEventId $expectedLastEventId): void
    {
        $this->getEventStore()->conditionalAppend(Events::fromArray(array_map(self::arrayToEvent(...), $events)), $query, $expectedLastEventId);
    }

    /**
     * @phpstan-param EventShape $event
     */
    final protected function conditionalAppendEvent(array $event, StreamQuery $query, ExpectedLastEventId $expectedLastEventId): void
    {
        $this->conditionalAppendEvents([$event], $query, $expectedLastEventId);
    }

    /**
     * @phpstan-param array<EventEnvelopeShape> $expectedEvents
     */
    final protected static function assertEventStream(EventStream $eventStream, array $expectedEvents): void
    {
        $actualEvents = [];
        $index = 0;
        foreach ($eventStream as $eventEnvelope) {
            $actualEvents[] = self::eventEnvelopeToArray(isset($expectedEvents[$index]) ? array_keys($expectedEvents[$index]) : ['id', 'type', 'data', 'domainIds', 'sequenceNumber'], $eventEnvelope);
            $index ++;
        }
        self::assertEquals($expectedEvents, $actualEvents);
    }


    // --- Internal ---

    private function getEventStore(): EventStore
    {
        if ($this->eventStore === null) {
            $this->eventStore = $this->createEventStore();
            //$this->eventStore->setup();
        }
        return $this->eventStore;
    }

    /**
     * @param string[] $keys
     * @phpstan-return EventEnvelopeShape
     */
    private static function eventEnvelopeToArray(array $keys, EventEnvelope $eventEnvelope): array
    {
        $supportedKeys = ['id', 'type', 'data', 'domainIds', 'sequenceNumber'];
        $unsupportedKeys = array_diff($keys, $supportedKeys);
        if ($unsupportedKeys !== []) {
            throw new InvalidArgumentException(sprintf('Invalid key(s) "%s" for expected event. Allowed keys are: "%s"', implode('", "', $unsupportedKeys), implode('", "', $supportedKeys)), 1684668588);
        }
        $actualAsArray = [
            'id' => $eventEnvelope->event->id->value,
            'type' => $eventEnvelope->event->type->value,
            'data' => $eventEnvelope->event->data->value,
            'domainIds' => $eventEnvelope->event->domainIds->toArray(),
            'sequenceNumber' => $eventEnvelope->sequenceNumber->value,
        ];
        foreach (array_diff($supportedKeys, $keys) as $unusedKey) {
            unset($actualAsArray[$unusedKey]);
        }
        return $actualAsArray;
    }

    /**
     * @phpstan-param EventShape $event
     */
    private static function arrayToEvent(array $event): Event
    {
        return new Event(
            isset($event['id']) ? EventId::fromString($event['id']) : EventId::create(),
            EventType::fromString($event['type'] ?? 'SomeEventType'),
            EventData::fromString($event['data'] ?? ''),
            DomainIds::fromArray($event['domainIds'] ?? [['foo' => 'bar']]),
        );
    }
}
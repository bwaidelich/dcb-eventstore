<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit;

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
use Wwwision\DCBEventStore\Model\SequenceNumber;
use Wwwision\DCBEventStore\StreamQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function array_map;
use function in_array;

/**
 * @phpstan-type EventShape array{id?: string, type?: string, data?: string, domainIds?: array<string, string>}
 * @phpstan-type EventEnvelopeShape array{id?: string, type?: string, data?: string, domainIds?: array<string, string>, sequenceNumber?: int}
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
abstract class EventStoreTestBase  extends TestCase
{

    private ?EventStore $eventStore = null;

    abstract protected function createEventStore(): EventStore;

    public function test_events(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->stream(new StreamQuery(null, null)), [
            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'domainIds' => ['foo' => 'bar', 'baz' => 'foos'], 'sequenceNumber' => 1],
            ['id' => 'id-b', 'data' => 'b', 'type' => 'SomeOtherEventType', 'domainIds' => ['foo' => 'bar'],'sequenceNumber' => 2],
            ['id' => 'id-c', 'data' => 'c', 'type' => 'SomeEventType', 'domainIds' => ['foo' => 'bar'],'sequenceNumber' => 3],
            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'domainIds' => ['foo' => 'bar', 'baz' => 'foos'],'sequenceNumber' => 4],
            ['id' => 'id-e', 'data' => 'e', 'type' => 'SomeEventType', 'domainIds' => ['foo' => 'bar', 'baz' => 'foos'],'sequenceNumber' => 5],
            ['id' => 'id-f', 'data' => 'f', 'type' => 'SomeOtherEventType', 'domainIds' => ['foo' => 'bar', 'baz' => 'foos'],'sequenceNumber' => 6],
        ]);
    }

    public function test_stream_allows_filtering_of_events_by_domain_ids(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->stream(new StreamQuery(DomainIds::single('baz', 'foos'), null)), [
            ['data' => 'a'],
            ['data' => 'd'],
            ['data' => 'e'],
            ['data' => 'f'],
        ]);
    }

    public function test_stream_allows_filtering_of_events_by_event_types(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->stream(new StreamQuery(null, EventTypes::single('SomeEventType'))), [
            ['data' => 'a'],
            ['data' => 'c'],
            ['data' => 'e'],
        ]);
    }

    public function test_stream_allows_filtering_of_events_by_domain_ids_and_event_types(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->stream(new StreamQuery(DomainIds::single('baz', 'foos'), EventTypes::single('SomeEventType'))), [
            ['data' => 'a'],
            ['data' => 'e'],
        ]);
    }

    public function test_conditionalAppend_fails_if_new_events_match_the_specified_query(): void
    {
        $this->appendDummyEvents();

        $query = new StreamQuery(DomainIds::single('baz', 'foos'), EventTypes::single('SomeEventType'));
        $stream = $this->getEventStore()->stream($query);
        $lastEventId = $stream->last()->event->id;

        $this->appendEvent(['type' => 'SomeEventType', 'domainIds' => ['baz' => 'foos']]);

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, $lastEventId);
    }

    public function test_conditionalAppend_fails_if_no_last_event_id_was_expected_but_query_matches_events(): void
    {
        $this->appendDummyEvents();

        $query = new StreamQuery(DomainIds::single('baz', 'foos'), EventTypes::single('SomeEventType'));

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, null);
    }

    public function test_conditionalAppend_fails_if_last_event_id_was_expected_but_query_matches_no_events(): void
    {
        $query = new StreamQuery(DomainIds::single('baz', 'foos'), EventTypes::single('SomeEventTypeThatDidNotOccur'));

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, EventId::create());
    }

    // --- Helpers ---

    final protected function appendDummyEvents(): void
    {
        $this->appendEvents(array_map(static fn ($char) => [
            'id' => 'id-' . $char,
            'data' => $char,
            'type' => in_array($char, ['a', 'c', 'e'], true) ? 'SomeEventType' : 'SomeOtherEventType',
            'domainIds' => in_array($char, ['b', 'c'], true) ? ['foo' => 'bar'] : ['foo' => 'bar', 'baz' => 'foos'],
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
    final protected function conditionalAppendEvents(array $events, StreamQuery $query, ?EventId $lastEventId): void
    {
        $this->getEventStore()->conditionalAppend(Events::fromArray(array_map(self::arrayToEvent(...), $events)), $query, $lastEventId);
    }

    /**
     * @phpstan-param EventShape $event
     */
    final protected function conditionalAppendEvent(array $event, StreamQuery $query, ?EventId $lastEventId): void
    {
        $this->conditionalAppendEvents([$event], $query, $lastEventId);
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
            $this->eventStore->setup();
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
            DomainIds::fromArray($event['domainIds'] ?? []),
        );
    }
}
<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration;

use InvalidArgumentException;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\EventMetadata;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\TagsCriterion;
use Wwwision\DCBEventStore\Types\Tag;
use Wwwision\DCBEventStore\Types\Tags;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventData;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\EventId;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\EventType;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\ExpectedHighestSequenceNumber;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function array_keys;
use function array_map;
use function in_array;
use function range;

/**
 * @phpstan-type EventShape array{id?: string, type?: string, data?: string, tags?: array<string>}
 * @phpstan-type EventEnvelopeShape array{id?: string, type?: string, data?: string, tags?: array<string>, sequenceNumber?: int}
 */
#[CoversClass(Tag::class)]
#[CoversClass(Tags::class)]
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
#[CoversClass(AppendCondition::class)]
#[CoversClass(EventMetadata::class)]
#[CoversClass(Criteria::class)]
#[CoversClass(TagsCriterion::class)]
#[CoversClass(EventTypesCriterion::class)]
#[CoversClass(EventTypesAndTagsCriterion::class)]
abstract class EventStoreTestBase extends TestCase
{

    private ?EventStore $eventStore = null;

    abstract protected function createEventStore(): EventStore;

    public function test_read_returns_an_empty_stream_if_no_events_were_published(): void
    {
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard()), []);
    }

    public function test_read_returns_all_events(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard()), [
            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
            ['id' => 'id-b', 'data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2],
            ['id' => 'id-c', 'data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3],
            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['id' => 'id-e', 'data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5],
            ['id' => 'id-f', 'data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6],
        ]);
    }

    public function test_read_allows_to_specify_minimum_sequenceNumber(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), SequenceNumber::fromInteger(4)), [
            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['id' => 'id-e', 'data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5],
            ['id' => 'id-f', 'data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6],
        ]);
    }

    public function test_read_returns_an_empty_stream_if_minimum_sequenceNumber_exceeds_highest(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), SequenceNumber::fromInteger(123)), []);
    }

    public function test_read_allows_filtering_of_events_by_tag(): void
    {
        $this->appendDummyEvents();
        $query = StreamQuery::create(Criteria::create(new TagsCriterion(Tags::fromArray(['baz:foos']))));
        self::assertEventStream($this->stream($query), [
            ['data' => 'a'],
            ['data' => 'd'],
            ['data' => 'e'],
            ['data' => 'f'],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_tags_disjunction(): void
    {
        $this->appendEvents([
            ['id' => 'a', 'tags' => ['foo:bar']],
            ['id' => 'b', 'tags' => ['foo:bar', 'baz:foos']],
            ['id' => 'c', 'tags' => ['baz:foos', 'foo:bar']],
            ['id' => 'd', 'tags' => ['baz:foos']],
            ['id' => 'e', 'tags' => ['baz:foosnot']],
            ['id' => 'f', 'tags' => ['foo:bar', 'baz:notfoos']],
            ['id' => 'g', 'tags' => ['baz:foos', 'foo:bar', 'foos:baz']],
            ['id' => 'h', 'tags' => ['baz:foosn', 'foo:notbar', 'foos:bar']],
        ]);
        $query = StreamQuery::create(Criteria::create(new TagsCriterion(Tags::fromArray(['foo:bar'])), new TagsCriterion(Tags::fromArray(['baz:foos']))));
        self::assertEventStream($this->stream($query), [
            ['id' => 'a'],
            ['id' => 'b'],
            ['id' => 'c'],
            ['id' => 'd'],
            ['id' => 'f'],
            ['id' => 'g'],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_tags_conjunction(): void
    {
        $this->appendEvents([
            ['id' => 'a', 'tags' => ['foo:bar']],
            ['id' => 'b', 'tags' => ['foo:bar', 'baz:foos']],
            ['id' => 'c', 'tags' => ['baz:foos', 'foo:bar']],
            ['id' => 'd', 'tags' => ['baz:foos']],
            ['id' => 'e', 'tags' => ['baz:foosnot']],
            ['id' => 'f', 'tags' => ['foo:bar', 'baz:notfoos']],
            ['id' => 'g', 'tags' => ['baz:foos', 'foo:bar', 'foos:baz']],
            ['id' => 'h', 'tags' => ['baz:foosn', 'foo:notbar', 'foos:bar']],
        ]);
        $query = StreamQuery::create(Criteria::create(new TagsCriterion(Tags::fromArray(['foo:bar', 'baz:foos']))));
        self::assertEventStream($this->stream($query), [
            ['id' => 'b'],
            ['id' => 'c'],
            ['id' => 'g'],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_event_types(): void
    {
        $this->appendDummyEvents();
        $query = StreamQuery::create(Criteria::create(new EventTypesCriterion(EventTypes::fromStrings('SomeEventType'))));
        self::assertEventStream($this->stream($query), [
            ['data' => 'a'],
            ['data' => 'c'],
            ['data' => 'e'],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_tags_and_event_types(): void
    {
        $this->appendDummyEvents();
        $query = StreamQuery::create(Criteria::create(new EventTypesAndTagsCriterion(EventTypes::fromStrings('SomeEventType'), Tags::create(Tag::fromString('baz:foos')))));
        self::assertEventStream($this->stream($query), [
            ['data' => 'a'],
            ['data' => 'e'],
        ]);
    }

    public function test_read_allows_fetching_no_events(): void
    {
        $this->appendDummyEvents();
        $query = StreamQuery::create(Criteria::create(new EventTypesCriterion(EventTypes::fromStrings('NonExistingEventType'))));
        self::assertEventStream($this->stream($query), []);
    }

//    // NOTE: This test is commented out because that guarantee is currently NOT given (it works on SQLite but not on MariaDB and PostgreSQL)
//    public function test_read_includes_events_that_where_appended_after_iteration_started(): void
//    {
//        $this->appendDummyEvents();
//        $actualEvents = [];
//        $index = 0;
//        $eventStream = $this->getEventStore()->read(StreamQuery::wildcard());
//        foreach ($eventStream as $eventEnvelope) {
//            $actualEvents[] = self::eventEnvelopeToArray(isset($expectedEvents[$index]) ? array_keys($expectedEvents[$index]) : ['id', 'type', 'data', 'tags', 'sequenceNumber'], $eventEnvelope);
//            if ($eventEnvelope->sequenceNumber->value === 3) {
//                $this->appendEvents([
//                    ['id' => 'id-g', 'data' => 'g', 'type' => 'SomeEventType', 'tags' => ['foo:bar', 'foo:baz']],
//                    ['id' => 'id-h', 'data' => 'h', 'type' => 'SomeOtherEventType', 'tags' => ['foo:foos', 'bar:baz']],
//                ]);
//            }
//            $index ++;
//        }
//        $expectedEvents = [
//            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
//            ['id' => 'id-b', 'data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2],
//            ['id' => 'id-c', 'data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3],
//            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
//            ['id' => 'id-e', 'data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5],
//            ['id' => 'id-f', 'data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6],
//            ['id' => 'id-g', 'data' => 'g', 'type' => 'SomeEventType', 'tags' => ['foo:bar', 'foo:baz'], 'sequenceNumber' => 7],
//            ['id' => 'id-h', 'data' => 'h', 'type' => 'SomeOtherEventType', 'tags' => ['bar:baz', 'foo:foos'], 'sequenceNumber' => 8],
//        ];
//        self::assertEquals($expectedEvents, $actualEvents);
//    }

    public function test_readBackwards_returns_all_events_in_descending_order(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->readBackwards(StreamQuery::wildcard()), [
            ['id' => 'id-f', 'data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6],
            ['id' => 'id-e', 'data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5],
            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['id' => 'id-c', 'data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3],
            ['id' => 'id-b', 'data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2],
            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
        ]);
    }

    public function test_readBackwards_allows_to_specify_maximum_sequenceNumber(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->readBackwards(StreamQuery::wildcard(), SequenceNumber::fromInteger(4)), [
            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['id' => 'id-c', 'data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3],
            ['id' => 'id-b', 'data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2],
            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
        ]);
    }

    public function test_readBackwards_returns_single_event_if_maximum_sequenceNumber_is_one(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->readBackwards(StreamQuery::wildcard(), SequenceNumber::fromInteger(1)), [
            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
        ]);
    }

    public function test_append_appends_event_if_expectedHighestSequenceNumber_matches(): void
    {
        $this->appendDummyEvents();

        $query = StreamQuery::create(Criteria::create(new EventTypesAndTagsCriterion(EventTypes::fromStrings('SomeEventType'), Tags::create(Tag::fromString('baz:foos')))));
        $stream = $this->getEventStore()->readBackwards($query);
        $lastSequenceNumber = $stream->first()->sequenceNumber;
        $this->conditionalAppendEvent(['type' => 'SomeEventType', 'data' => 'new event', 'tags' => ['baz:foos']], $query, ExpectedHighestSequenceNumber::fromSequenceNumber($lastSequenceNumber));

        self::assertEventStream($this->getEventStore()->read($query), [
            ['data' => 'a'],
            ['data' => 'e'],
            ['data' => 'new event'],
        ]);
    }

    public function test_append_fails_if_new_events_match_the_specified_query(): void
    {
        $this->appendDummyEvents();

        $query = StreamQuery::create(Criteria::create(new EventTypesAndTagsCriterion(EventTypes::fromStrings('SomeEventType'), Tags::create(Tag::fromString('baz:foos')))));
        $stream = $this->getEventStore()->readBackwards($query);
        $lastSequenceNumber = $stream->first()->sequenceNumber;

        $this->appendEvent(['type' => 'SomeEventType', 'tags' => ['baz:foos']]);

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedHighestSequenceNumber::fromSequenceNumber($lastSequenceNumber));
    }

    public function test_append_fails_if_no_last_event_id_was_expected_but_query_matches_events(): void
    {
        $this->appendDummyEvents();

        $query = StreamQuery::create(Criteria::create(new EventTypesAndTagsCriterion(EventTypes::fromStrings('SomeEventType'), Tags::create(Tag::fromString('baz:foos')))));

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedHighestSequenceNumber::none());
    }

    public function test_append_fails_if_last_event_id_was_expected_but_query_matches_no_events(): void
    {
        $query = StreamQuery::create(Criteria::create(new EventTypesAndTagsCriterion(EventTypes::fromStrings('SomeEventTypeThatDidNotOccur'), Tags::create(Tag::fromString('baz:foos')))));

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedHighestSequenceNumber::fromInteger(123));
    }

    // --- Helpers ---

    final protected function streamAll(): EventStream
    {
        return $this->getEventStore()->read(StreamQuery::wildcard());
    }

    final protected function parseQuery(string $query): StreamQuery
    {
        $criteria = StreamQueryParser::parse($query);
        return StreamQuery::create($criteria);
    }

    final protected function stream(StreamQuery $query): EventStream
    {
        return $this->getEventStore()->read($query);
    }

    final protected function appendDummyEvents(): void
    {
        $this->appendEvents(array_map(static fn ($char) => [
            'id' => 'id-' . $char,
            'data' => $char,
            'type' => in_array($char, ['a', 'c', 'e'], true) ? 'SomeEventType' : 'SomeOtherEventType',
            'tags' => in_array($char, ['b', 'c'], true) ? ['foo:bar'] : ['foo:bar', 'baz:foos'],
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
        $this->getEventStore()->append(Events::fromArray(array_map(self::arrayToEvent(...), $events)), AppendCondition::noConstraints());
    }

    /**
     * @phpstan-param EventShape $event
     */
    final protected function conditionalAppendEvents(array $events, StreamQuery $query, ExpectedHighestSequenceNumber $expectedHighestSequenceNumber): void
    {
        $this->getEventStore()->append(Events::fromArray(array_map(self::arrayToEvent(...), $events)), new AppendCondition($query, $expectedHighestSequenceNumber));
    }

    /**
     * @phpstan-param EventShape $event
     */
    final protected function conditionalAppendEvent(array $event, StreamQuery $query, ExpectedHighestSequenceNumber $expectedHighestSequenceNumber): void
    {
        $this->conditionalAppendEvents([$event], $query, $expectedHighestSequenceNumber);
    }

    /**
     * @phpstan-param array<EventEnvelopeShape> $expectedEvents
     */
    final protected static function assertEventStream(EventStream $eventStream, array $expectedEvents): void
    {
        $actualEvents = [];
        $index = 0;
        foreach ($eventStream as $eventEnvelope) {
            $actualEvents[] = self::eventEnvelopeToArray(isset($expectedEvents[$index]) ? array_keys($expectedEvents[$index]) : ['id', 'type', 'data', 'tags', 'sequenceNumber'], $eventEnvelope);
            $index ++;
        }
        self::assertEquals($expectedEvents, $actualEvents);
    }

    // --- Internal ---

    private function getEventStore(): EventStore
    {
        if ($this->eventStore === null) {
            $this->eventStore = $this->createEventStore();
        }
        return $this->eventStore;
    }

    /**
     * @param string[] $keys
     * @phpstan-return EventEnvelopeShape
     */
    private static function eventEnvelopeToArray(array $keys, EventEnvelope $eventEnvelope): array
    {
        $supportedKeys = ['id', 'type', 'data', 'tags', 'sequenceNumber'];
        $unsupportedKeys = array_diff($keys, $supportedKeys);
        if ($unsupportedKeys !== []) {
            throw new InvalidArgumentException(sprintf('Invalid key(s) "%s" for expected event. Allowed keys are: "%s"', implode('", "', $unsupportedKeys), implode('", "', $supportedKeys)), 1684668588);
        }
        $actualAsArray = [
            'id' => $eventEnvelope->event->id->value,
            'type' => $eventEnvelope->event->type->value,
            'data' => $eventEnvelope->event->data->value,
            'tags' => $eventEnvelope->event->tags->toSimpleArray(),
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
            Tags::fromArray($event['tags'] ?? ['foo:bar']),
            EventMetadata::fromArray($event['metadata'] ?? ['foo' => 'bar']),
        );
    }
}
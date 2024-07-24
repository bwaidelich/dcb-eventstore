<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration;

use Hoa\File\Read;
use InvalidArgumentException;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\EventEnvelopes;
use Wwwision\DCBEventStore\Types\EventMetadata;
use Wwwision\DCBEventStore\Types\ReadOptions;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criterion;
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
 * @phpstan-type EventEnvelopeShape array{id?: string, type?: string, data?: string, tags?: array<string>, sequenceNumber?: int, criteria?: array<Criterion>}
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
            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1, 'criteria' => []],
            ['id' => 'id-b', 'data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2, 'criteria' => []],
            ['id' => 'id-c', 'data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3, 'criteria' => []],
            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4, 'criteria' => []],
            ['id' => 'id-e', 'data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5, 'criteria' => []],
            ['id' => 'id-f', 'data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6, 'criteria' => []],
        ]);
    }

    public function test_read_allows_to_specify_minimum_sequenceNumber(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), ReadOptions::create(from: SequenceNumber::fromInteger(4))), [
            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['id' => 'id-e', 'data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5],
            ['id' => 'id-f', 'data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6],
        ]);
    }

    public function test_read_returns_an_empty_stream_if_minimum_sequenceNumber_exceeds_highest(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), ReadOptions::create(from: SequenceNumber::fromInteger(123))), []);
    }

    public function test_read_allows_filtering_of_events_by_tag(): void
    {
        $this->appendDummyEvents();
        $tagsCriterion = EventTypesAndTagsCriterion::create(tags: ['baz:foos']);
        $query = StreamQuery::create(Criteria::create($tagsCriterion));
        self::assertEventStream($this->stream($query), [
            ['data' => 'a', 'criteria' => [$tagsCriterion]],
            ['data' => 'd', 'criteria' => [$tagsCriterion]],
            ['data' => 'e', 'criteria' => [$tagsCriterion]],
            ['data' => 'f', 'criteria' => [$tagsCriterion]],
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
        $tagsCriterion1 = EventTypesAndTagsCriterion::create(tags: ['foo:bar']);
        $tagsCriterion2 = EventTypesAndTagsCriterion::create(tags: ['baz:foos']);
        $query = StreamQuery::create(Criteria::create($tagsCriterion1, $tagsCriterion2));
        self::assertEventStream($this->stream($query), [
            ['id' => 'a', 'criteria' => [$tagsCriterion1]],
            ['id' => 'b', 'criteria' => [$tagsCriterion1, $tagsCriterion2]],
            ['id' => 'c', 'criteria' => [$tagsCriterion1, $tagsCriterion2]],
            ['id' => 'd', 'criteria' => [$tagsCriterion2]],
            ['id' => 'f', 'criteria' => [$tagsCriterion1]],
            ['id' => 'g', 'criteria' => [$tagsCriterion1, $tagsCriterion2]],
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
        $tagsCriterion = EventTypesAndTagsCriterion::create(tags: ['foo:bar', 'baz:foos']);
        $query = StreamQuery::create(Criteria::create($tagsCriterion));
        self::assertEventStream($this->stream($query), [
            ['id' => 'b', 'criteria' => [$tagsCriterion]],
            ['id' => 'c', 'criteria' => [$tagsCriterion]],
            ['id' => 'g', 'criteria' => [$tagsCriterion]],
        ]);
    }

    public function test_read_allows_filtering_of_last_event_by_tag(): void
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
        $tagsCriterion = EventTypesAndTagsCriterion::create(tags: ['foo:bar', 'baz:foos'], onlyLastEvent: true);
        $query = StreamQuery::create(Criteria::create($tagsCriterion));
        self::assertEventStream($this->stream($query), [
            ['id' => 'g', 'criteria' => [$tagsCriterion]],
        ]);
    }


    public function test_read_allows_filtering_of_events_by_event_types(): void
    {
        $this->appendDummyEvents();
        $eventTypesCriterion = EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType']);
        $query = StreamQuery::create(Criteria::create($eventTypesCriterion));
        self::assertEventStream($this->stream($query), [
            ['data' => 'a', 'criteria' => [$eventTypesCriterion]],
            ['data' => 'c', 'criteria' => [$eventTypesCriterion]],
            ['data' => 'e', 'criteria' => [$eventTypesCriterion]],
        ]);
    }

    public function test_read_allows_filtering_of_last_event_by_event_types(): void
    {
        $this->appendDummyEvents();
        $eventTypesCriterion = EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType'], onlyLastEvent: true);
        $query = StreamQuery::create(Criteria::create($eventTypesCriterion));
        self::assertEventStream($this->stream($query), [
            ['data' => 'e', 'criteria' => [$eventTypesCriterion]],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_tags_and_event_types(): void
    {
        $this->appendDummyEvents();
        $eventTypesAndTagsCriterion = EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventType', tags: 'baz:foos');
        $query = StreamQuery::create(Criteria::create($eventTypesAndTagsCriterion));
        self::assertEventStream($this->stream($query), [
            ['data' => 'a', 'criteria' => [$eventTypesAndTagsCriterion]],
            ['data' => 'e', 'criteria' => [$eventTypesAndTagsCriterion]],
        ]);
    }

    public function test_read_allows_filtering_of_last_event_by_tags_and_event_types(): void
    {
        $this->appendDummyEvents();
        $eventTypesAndTagsCriterion = EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventType', tags: 'baz:foos', onlyLastEvent: true);
        $query = StreamQuery::create(Criteria::create($eventTypesAndTagsCriterion));
        self::assertEventStream($this->stream($query), [
            ['data' => 'e', 'criteria' => [$eventTypesAndTagsCriterion]],
        ]);
    }

    public function test_read_allows_fetching_no_events(): void
    {
        $this->appendDummyEvents();
        $query = StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: ['NonExistingEventType'])));
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

    public function test_read_backwards_returns_all_events_in_descending_order(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), ReadOptions::create(backwards: true)), [
            ['id' => 'id-f', 'data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6, 'criteria' => []],
            ['id' => 'id-e', 'data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5, 'criteria' => []],
            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4, 'criteria' => []],
            ['id' => 'id-c', 'data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3, 'criteria' => []],
            ['id' => 'id-b', 'data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2, 'criteria' => []],
            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1, 'criteria' => []],
        ]);
    }

    public function test_read_backwards_allows_to_specify_maximum_sequenceNumber(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), ReadOptions::create(from: SequenceNumber::fromInteger(4), backwards: true)), [
            ['id' => 'id-d', 'data' => 'd', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['id' => 'id-c', 'data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3],
            ['id' => 'id-b', 'data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2],
            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
        ]);
    }

    public function test_read_backwards_returns_single_event_if_maximum_sequenceNumber_is_one(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), ReadOptions::create(from: SequenceNumber::fromInteger(1), backwards: true)), [
            ['id' => 'id-a', 'data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
        ]);
    }

    public function test_read_options_dont_affect_matching_events(): void
    {
        $this->appendEvents([
            ['id' => 'a', 'type' => 'Type1', 'tags' => ['foo:bar']],
            ['id' => 'b', 'type' => 'Type2', 'tags' => ['foo:bar', 'baz:foos']],
            ['id' => 'c', 'type' => 'Type3', 'tags' => ['baz:foos', 'foo:bar']],
            ['id' => 'd', 'type' => 'Type1', 'tags' => ['baz:foos']],
            ['id' => 'e', 'type' => 'Type2', 'tags' => ['baz:foosnot']],
            ['id' => 'f', 'type' => 'Type2', 'tags' => ['foo:bar', 'baz:notfoos']],
            ['id' => 'g', 'type' => 'Type1', 'tags' => ['baz:foos', 'foo:bar', 'foos:baz']],
            ['id' => 'h', 'type' => 'Type3', 'tags' => ['baz:foosn', 'foo:notbar', 'foos:bar']],
        ]);
        $criterion1 = EventTypesAndTagsCriterion::create(tags: ['foo:bar'], onlyLastEvent: true);
        $criterion2 = EventTypesAndTagsCriterion::create(eventTypes: ['Type2', 'Type1'], tags: ['foo:bar']);
        $query = StreamQuery::create(Criteria::create($criterion1, $criterion2));

        /** @var EventEnvelopeShape[] $expectedEvents */
        $expectedEvents = [
            ['id' => 'a', 'criteria' => [$criterion2]],
            ['id' => 'b', 'criteria' => [$criterion2]],
            ['id' => 'f', 'criteria' => [$criterion2]],
            ['id' => 'g', 'criteria' => [$criterion2, $criterion1]],
        ];
        self::assertEventStream($this->stream($query), $expectedEvents);

        self::assertEventStream($this->stream($query, ReadOptions::create(backwards: true)), array_reverse($expectedEvents));
        self::assertEventStream($this->stream($query, ReadOptions::create(from: SequenceNumber::fromInteger(3))), array_slice($expectedEvents, 2));
        self::assertEventStream($this->stream($query, ReadOptions::create(from: SequenceNumber::fromInteger(3), backwards: true)), array_slice(array_reverse($expectedEvents), 2));
    }

    public function test_append_appends_event_if_expectedHighestSequenceNumber_matches(): void
    {
        $this->appendDummyEvents();

        $eventTypesAndTagsCriterion = EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventType', tags: 'baz:foos');
        $query = StreamQuery::create(Criteria::create($eventTypesAndTagsCriterion));
        $stream = $this->getEventStore()->read($query, ReadOptions::create(backwards: true));
        $lastEvent = $stream->first();
        self::assertInstanceOf(EventEnvelope::class, $lastEvent);
        $lastSequenceNumber = $lastEvent->sequenceNumber;
        $this->conditionalAppendEvent(['type' => 'SomeEventType', 'data' => 'new event', 'tags' => ['baz:foos']], $query, ExpectedHighestSequenceNumber::fromSequenceNumber($lastSequenceNumber));

        self::assertEventStream($this->getEventStore()->read($query), [
            ['data' => 'a', 'criteria' => [$eventTypesAndTagsCriterion]],
            ['data' => 'e', 'criteria' => [$eventTypesAndTagsCriterion]],
            ['data' => 'new event', 'criteria' => [$eventTypesAndTagsCriterion]],
        ]);
    }

    public function test_append_fails_if_new_events_match_the_specified_query(): void
    {
        $this->appendDummyEvents();

        $query = StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventType', tags: 'baz:foos')));
        $stream = $this->getEventStore()->read($query, ReadOptions::create(backwards: true));
        $lastEvent = $stream->first();
        self::assertInstanceOf(EventEnvelope::class, $lastEvent);
        $lastSequenceNumber = $lastEvent->sequenceNumber;

        $this->appendEvent(['type' => 'SomeEventType', 'tags' => ['baz:foos']]);

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedHighestSequenceNumber::fromSequenceNumber($lastSequenceNumber));
    }

    public function test_append_fails_if_no_last_event_id_was_expected_but_query_matches_events(): void
    {
        $this->appendDummyEvents();

        $query = StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventType', tags: 'baz:foos')));

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedHighestSequenceNumber::none());
    }

    public function test_append_fails_if_last_event_id_was_expected_but_query_matches_no_events(): void
    {
        $query = StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventTypeThatDidNotOccur', tags: 'baz:foos')));

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedHighestSequenceNumber::fromInteger(123));
    }

    // --- Helpers ---

    final protected function streamAll(): EventStream
    {
        return $this->getEventStore()->read(StreamQuery::wildcard());
    }

    final protected function stream(StreamQuery $query, ReadOptions $options = null): EventStream
    {
        return $this->getEventStore()->read($query, $options);
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
     * @phpstan-param EventShape[] $events
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
    final protected static function assertEventStream(EventStream|EventEnvelopes $eventStream, array $expectedEvents): void
    {
        $actualEvents = [];
        $index = 0;
        foreach ($eventStream as $eventEnvelope) {
            $actualEvents[] = self::eventEnvelopeToArray(isset($expectedEvents[$index]) ? array_keys($expectedEvents[$index]) : ['id', 'type', 'data', 'tags', 'sequenceNumber', 'criteria'], $eventEnvelope);
            $index ++;
        }
        $expectedEventsWithCriterionHashes = array_map(static function (array $expectedEvent) {
            if (array_key_exists('criteria', $expectedEvent)) {
                $expectedEvent['criteria'] = array_map(static fn (Criterion $criterion) => $criterion->hash()->value, $expectedEvent['criteria']);
            }
            return $expectedEvent;
        }, $expectedEvents);
        self::assertEquals($expectedEventsWithCriterionHashes, $actualEvents);
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
        $supportedKeys = ['id', 'type', 'data', 'tags', 'sequenceNumber', 'criteria'];
        $unsupportedKeys = array_diff($keys, $supportedKeys);
        if ($unsupportedKeys !== []) {
            throw new InvalidArgumentException(sprintf('Invalid key(s) "%s" for expected event. Allowed keys are: "%s"', implode('", "', $unsupportedKeys), implode('", "', $supportedKeys)), 1684668588);
        }
        $criterionHashes = $eventEnvelope->criterionHashes->toStringArray();
        sort($criterionHashes);
        $actualAsArray = [
            'id' => $eventEnvelope->event->id->value,
            'type' => $eventEnvelope->event->type->value,
            'data' => $eventEnvelope->event->data->value,
            'tags' => $eventEnvelope->event->tags->toSimpleArray(),
            'sequenceNumber' => $eventEnvelope->sequenceNumber->value,
            'criteria' => $criterionHashes,
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
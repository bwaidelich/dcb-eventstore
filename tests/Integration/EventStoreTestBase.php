<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventData;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\EventEnvelopes;
use Wwwision\DCBEventStore\Types\EventMetadata;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\EventType;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\ExpectedHighestSequenceNumber;
use Wwwision\DCBEventStore\Types\ReadOptions;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBEventStore\Types\Tag;
use Wwwision\DCBEventStore\Types\Tags;

use function array_keys;
use function array_map;
use function in_array;
use function range;

/**
 * @phpstan-type EventShape array{type?: string, data?: string, tags?: array<string>}
 * @phpstan-type EventEnvelopeShape array{type?: string, data?: string, tags?: array<string>, sequenceNumber?: int}
 */
#[CoversClass(Tag::class)]
#[CoversClass(Tags::class)]
#[CoversClass(EventData::class)]
#[CoversClass(ConditionalAppendFailed::class)]
#[CoversClass(EventEnvelope::class)]
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
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3],
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5],
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6],
        ]);
    }

    public function test_read_allows_to_specify_minimum_sequenceNumber(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), ReadOptions::create(from: SequenceNumber::fromInteger(4))), [
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5],
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6],
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
            ['data' => 'a'],
            ['data' => 'd'],
            ['data' => 'e'],
            ['data' => 'f'],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_tags_disjunction(): void
    {
        $this->appendEvents([
            ['tags' => ['foo:bar']],
            ['tags' => ['foo:bar', 'baz:foos']],
            ['tags' => ['baz:foos', 'foo:bar']],
            ['tags' => ['baz:foos']],
            ['tags' => ['baz:foosnot']],
            ['tags' => ['foo:bar', 'baz:notfoos']],
            ['tags' => ['baz:foos', 'foo:bar', 'foos:baz']],
            ['tags' => ['baz:foosn', 'foo:notbar', 'foos:bar']],
        ]);
        $tagsCriterion1 = EventTypesAndTagsCriterion::create(tags: ['foo:bar']);
        $tagsCriterion2 = EventTypesAndTagsCriterion::create(tags: ['baz:foos']);
        $query = StreamQuery::create(Criteria::create($tagsCriterion1, $tagsCriterion2));
        self::assertEventStream($this->stream($query), [
            ['sequenceNumber' => 1],
            ['sequenceNumber' => 2],
            ['sequenceNumber' => 3],
            ['sequenceNumber' => 4],
            ['sequenceNumber' => 6],
            ['sequenceNumber' => 7],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_tags_conjunction(): void
    {
        $this->appendEvents([
            ['tags' => ['foo:bar']],
            ['tags' => ['foo:bar', 'baz:foos']],
            ['tags' => ['baz:foos', 'foo:bar']],
            ['tags' => ['baz:foos']],
            ['tags' => ['baz:foosnot']],
            ['tags' => ['foo:bar', 'baz:notfoos']],
            ['tags' => ['baz:foos', 'foo:bar', 'foos:baz']],
            ['tags' => ['baz:foosn', 'foo:notbar', 'foos:bar']],
        ]);
        $tagsCriterion = EventTypesAndTagsCriterion::create(tags: ['foo:bar', 'baz:foos']);
        $query = StreamQuery::create(Criteria::create($tagsCriterion));
        self::assertEventStream($this->stream($query), [
            ['sequenceNumber' => 2],
            ['sequenceNumber' => 3],
            ['sequenceNumber' => 7],
        ]);
    }

    #[Group('feature_onlyLastEvent')]
    public function test_read_allows_filtering_of_last_event_by_tag(): void
    {
        $this->appendEvents([
            ['data' => 'a', 'tags' => ['foo:bar']],
            ['data' => 'b', 'tags' => ['foo:bar', 'baz:foos']],
            ['data' => 'c', 'tags' => ['baz:foos', 'foo:bar']],
            ['data' => 'd', 'tags' => ['baz:foos']],
            ['data' => 'e', 'tags' => ['baz:foosnot']],
            ['data' => 'f', 'tags' => ['foo:bar', 'baz:notfoos']],
            ['data' => 'g', 'tags' => ['baz:foos', 'foo:bar', 'foos:baz']],
            ['data' => 'h', 'tags' => ['baz:foosn', 'foo:notbar', 'foos:bar']],
        ]);
        $tagsCriterion = EventTypesAndTagsCriterion::create(tags: ['foo:bar', 'baz:foos'], onlyLastEvent: true);
        $query = StreamQuery::create(Criteria::create($tagsCriterion));
        self::assertEventStream($this->stream($query), [
            ['data' => 'g'],
        ]);
    }


    public function test_read_allows_filtering_of_events_by_event_type(): void
    {
        $this->appendDummyEvents();
        $eventTypesCriterion = EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType', 'SomeOtherEventType']);
        $query = StreamQuery::create(Criteria::create($eventTypesCriterion));
        self::assertEventStream($this->stream($query), [
            ['data' => 'a'],
            ['data' => 'b'],
            ['data' => 'c'],
            ['data' => 'e'],
            ['data' => 'f'],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_multiple_event_types(): void
    {
        $this->appendDummyEvents();
        $eventTypesCriterion = EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType']);
        $query = StreamQuery::create(Criteria::create($eventTypesCriterion));
        self::assertEventStream($this->stream($query), [
            ['data' => 'a'],
            ['data' => 'c'],
            ['data' => 'e'],
        ]);
    }

    #[Group('feature_onlyLastEvent')]
    public function test_read_allows_filtering_of_last_event_by_event_types(): void
    {
        $this->appendDummyEvents();
        $eventTypesCriterion = EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType'], onlyLastEvent: true);
        $query = StreamQuery::create(Criteria::create($eventTypesCriterion));
        self::assertEventStream($this->stream($query), [
            ['data' => 'e'],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_tags_and_event_types(): void
    {
        $this->appendDummyEvents();
        $eventTypesAndTagsCriterion = EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventType', tags: 'baz:foos');
        $query = StreamQuery::create(Criteria::create($eventTypesAndTagsCriterion));
        self::assertEventStream($this->stream($query), [
            ['data' => 'a'],
            ['data' => 'e'],
        ]);
    }

    #[Group('feature_onlyLastEvent')]
    public function test_read_allows_filtering_of_last_event_by_tags_and_event_types(): void
    {
        $this->appendDummyEvents();
        $eventTypesAndTagsCriterion = EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventType', tags: 'baz:foos', onlyLastEvent: true);
        $query = StreamQuery::create(Criteria::create($eventTypesAndTagsCriterion));
        self::assertEventStream($this->stream($query), [
            ['sequenceNumber' => 5, 'data' => 'e'],
        ]);
    }

    #[Group('feature_onlyLastEvent')]
    public function test_read_allows_filtering_of_last_events_by_tags_and_event_types_with_multiple_criteria(): void
    {
        $this->appendDummyEvents();
        $eventTypesAndTagsCriterion1 = EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventType', tags: 'baz:foos', onlyLastEvent: true);
        $eventTypesAndTagsCriterion2 = EventTypesAndTagsCriterion::create(eventTypes: 'SomeOtherEventType', tags: 'foo:bar', onlyLastEvent: true);
        $query = StreamQuery::create(Criteria::create($eventTypesAndTagsCriterion1, $eventTypesAndTagsCriterion2));
        self::assertEventStream($this->stream($query), [
            ['sequenceNumber' => 5, 'data' => 'e'],
            ['sequenceNumber' => 6, 'data' => 'f'],
        ]);
    }

    public function test_read_allows_fetching_no_events(): void
    {
        $this->appendDummyEvents();
        $query = StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: ['NonExistingEventType'])));
        self::assertEventStream($this->stream($query), []);
    }

    #[Group('feature_liveStream')]
    public function test_read_includes_events_that_where_appended_after_iteration_started(): void
    {
        $this->appendDummyEvents();
        $expectedEvents = [
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3],
            ['data' => 'd', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5],
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6],
            ['data' => 'g', 'type' => 'SomeEventType', 'tags' => ['foo:bar', 'foo:baz'], 'sequenceNumber' => 7],
            ['data' => 'h', 'type' => 'SomeOtherEventType', 'tags' => ['bar:baz', 'foo:foos'], 'sequenceNumber' => 8],
        ];
        $actualEvents = [];
        $index = 0;
        $eventStream = $this->getEventStore()->read(StreamQuery::wildcard());
        foreach ($eventStream as $eventEnvelope) {
            $actualEvents[] = self::eventEnvelopeToArray(isset($expectedEvents[$index]) ? array_keys($expectedEvents[$index]) : ['type', 'data', 'tags', 'sequenceNumber'], $eventEnvelope);
            if ($eventEnvelope->sequenceNumber->value === 3) {
                $this->appendEvents([
                    ['data' => 'g', 'type' => 'SomeEventType', 'tags' => ['foo:bar', 'foo:baz']],
                    ['data' => 'h', 'type' => 'SomeOtherEventType', 'tags' => ['foo:foos', 'bar:baz']],
                ]);
            }
            $index ++;
        }

        self::assertEquals($expectedEvents, $actualEvents);
    }

    public function test_read_backwards_returns_all_events_in_descending_order(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), ReadOptions::create(backwards: true)), [
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 6],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 5],
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2],
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
        ]);
    }

    public function test_read_backwards_allows_to_specify_maximum_sequenceNumber(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), ReadOptions::create(from: SequenceNumber::fromInteger(4), backwards: true)), [
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 4],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 3],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'sequenceNumber' => 2],
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
        ]);
    }

    public function test_read_backwards_returns_single_event_if_maximum_sequenceNumber_is_one(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard(), ReadOptions::create(from: SequenceNumber::fromInteger(1), backwards: true)), [
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'sequenceNumber' => 1],
        ]);
    }

    #[Group('feature_onlyLastEvent')]
    #[Group('feature_onlyLastEventCombined')]
    public function test_read_options_dont_affect_matching_events(): void
    {
        $this->appendEvents([
            ['data' => 'a', 'type' => 'Type1', 'tags' => ['foo:bar']],
            ['data' => 'b', 'type' => 'Type2', 'tags' => ['foo:bar', 'baz:foos']],
            ['data' => 'c', 'type' => 'Type3', 'tags' => ['baz:foos', 'foo:bar']],
            ['data' => 'd', 'type' => 'Type1', 'tags' => ['baz:foos']],
            ['data' => 'e', 'type' => 'Type2', 'tags' => ['baz:foosnot']],
            ['data' => 'f', 'type' => 'Type2', 'tags' => ['foo:bar', 'baz:notfoos']],
            ['data' => 'g', 'type' => 'Type1', 'tags' => ['baz:foos', 'foo:bar', 'foos:baz']],
            ['data' => 'h', 'type' => 'Type3', 'tags' => ['baz:foosn', 'foo:notbar', 'foos:bar']],
        ]);
        $criterion1 = EventTypesAndTagsCriterion::create(tags: ['foo:bar'], onlyLastEvent: true);
        $criterion2 = EventTypesAndTagsCriterion::create(eventTypes: ['Type2', 'Type1'], tags: ['foo:bar']);
        $query = StreamQuery::create(Criteria::create($criterion1, $criterion2));

        /** @var EventEnvelopeShape[] $expectedEvents */
        $expectedEvents = [
            ['data' => 'a', 'sequenceNumber' => 1],
            ['data' => 'b', 'sequenceNumber' => 2],
            ['data' => 'f', 'sequenceNumber' => 6],
            ['data' => 'g', 'sequenceNumber' => 7],
        ];
        self::assertEventStream($this->stream($query), $expectedEvents);

        self::assertEventStream($this->stream($query, ReadOptions::create(backwards: true)), array_reverse($expectedEvents));
        self::assertEventStream($this->stream($query, ReadOptions::create(from: 3)), array_slice($expectedEvents, 2));
        self::assertEventStream($this->stream($query, ReadOptions::create(from: 3, backwards: true)), array_slice(array_reverse($expectedEvents), 2));
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
            ['data' => 'a'],
            ['data' => 'e'],
            ['data' => 'new event'],
        ]);
    }

    #[Group('feature_appendConditionWithoutMatchingEvent')]
    public function test_append_appends_event_if_expected_highest_sequence_number_matches_no_events(): void
    {
        $query = StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventTypeThatDidNotOccur', tags: 'baz:foos')));
        $this->conditionalAppendEvent(['type' => 'SomeEventType', 'data' => 'new event'], $query, ExpectedHighestSequenceNumber::fromInteger(123));
        self::assertEventStream($this->getEventStore()->read(StreamQuery::wildcard()), [
            ['data' => 'new event'],
        ]);
    }

    public function test_append_fails_if_new_events_match_the_specified_query(): void
    {
        $this->appendDummyEvents();

        $query = StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(tags: 'baz:foos')));
        $stream = $this->getEventStore()->read($query, ReadOptions::create(backwards: true));
        $lastEvent = $stream->first();
        self::assertInstanceOf(EventEnvelope::class, $lastEvent);
        $lastSequenceNumber = $lastEvent->sequenceNumber;

        $this->appendEvent(['type' => 'SomeEventType', 'tags' => ['baz:foos']]);

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedHighestSequenceNumber::fromSequenceNumber($lastSequenceNumber));
    }

    public function test_append_fails_if_no_highest_sequence_number_was_expected_but_query_matches_events(): void
    {
        $this->appendDummyEvents();

        $query = StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: 'SomeEventType', tags: 'baz:foos')));

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, ExpectedHighestSequenceNumber::none());
    }

    // --- Helpers ---

    final protected function streamAll(): EventStream
    {
        return $this->getEventStore()->read(StreamQuery::wildcard());
    }

    final protected function stream(StreamQuery $query, ReadOptions|null $options = null): EventStream
    {
        return $this->getEventStore()->read($query, $options);
    }

    final protected function appendDummyEvents(): void
    {
        $this->appendEvents(array_map(static fn ($char) => [
            'data' => $char,
            'type' => match ($char) {
                'a', 'c', 'e' => 'SomeEventType',
                'd' => 'SomeThirdEventType',
                default => 'SomeOtherEventType',
            },
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
            $actualEvents[] = self::eventEnvelopeToArray(isset($expectedEvents[$index]) ? array_keys($expectedEvents[$index]) : ['type', 'data', 'tags', 'sequenceNumber'], $eventEnvelope);
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
        $supportedKeys = ['type', 'data', 'tags', 'sequenceNumber'];
        $unsupportedKeys = array_diff($keys, $supportedKeys);
        if ($unsupportedKeys !== []) {
            throw new InvalidArgumentException(sprintf('Invalid key(s) "%s" for expected event. Allowed keys are: "%s"', implode('", "', $unsupportedKeys), implode('", "', $supportedKeys)), 1684668588);
        }
        $actualAsArray = [
            'type' => $eventEnvelope->event->type->value,
            'data' => $eventEnvelope->event->data->value,
            'tags' => $eventEnvelope->event->tags->toStrings(),
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
        return Event::create(
            $event['type'] ?? 'SomeEventType',
            $event['data'] ?? '',
            $event['tags'] ?? ['foo:bar'],
            $event['metadata'] ?? ['foo' => 'bar'],
        );
    }
}
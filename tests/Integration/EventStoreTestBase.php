<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Wwwision\DCBEventStore\AppendCondition\AppendCondition;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\EventData;
use Wwwision\DCBEventStore\Event\EventMetadata;
use Wwwision\DCBEventStore\Event\Events;
use Wwwision\DCBEventStore\Event\EventType;
use Wwwision\DCBEventStore\Event\EventTypes;
use Wwwision\DCBEventStore\Event\SequencePosition;
use Wwwision\DCBEventStore\Event\Tag;
use Wwwision\DCBEventStore\Event\Tags;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;
use Wwwision\DCBEventStore\ReadOptions;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvent;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvents;

use function array_keys;
use function array_map;
use function in_array;
use function range;

/**
 * @phpstan-type EventShape array{type?: string, data?: string, tags?: array<string>, metadata?: array<string, mixed>}
 * @phpstan-type SequencedEventShape array{type?: string, data?: string, tags?: array<string>, position?: int, metadata?: array<string, mixed>, recordedAt?: DateTimeImmutable}
 */
#[CoversClass(Tag::class)]
#[CoversClass(Tags::class)]
#[CoversClass(EventData::class)]
#[CoversClass(ConditionalAppendFailed::class)]
#[CoversClass(SequencedEvent::class)]
#[CoversClass(EventType::class)]
#[CoversClass(EventTypes::class)]
#[CoversClass(Event::class)]
#[CoversClass(Events::class)]
#[CoversClass(SequencePosition::class)]
#[CoversClass(Query::class)]
#[CoversClass(AppendCondition::class)]
#[CoversClass(EventMetadata::class)]
#[CoversClass(QueryItem::class)]
abstract class EventStoreTestBase extends TestCase
{
    private EventStore|null $eventStore = null;

    private DateTimeImmutable|null $now = null;

    final protected function getTestClock(): ClockInterface
    {
        $nowClosure = fn() => $this->now;
        return new readonly class ($nowClosure) implements ClockInterface {
            /** @param Closure(): (DateTimeImmutable|null) $nowClosure */
            public function __construct(private Closure $nowClosure) {}
            public function now(): DateTimeImmutable
            {
                return ($this->nowClosure)() ?? new DateTimeImmutable();
            }
        };
    }

    abstract protected function createEventStore(): EventStore;

    public function test_read_returns_an_empty_stream_if_no_events_were_published(): void
    {
        self::assertEventStream($this->streamAll(), []);
    }

    public function test_read_returns_all_events(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->streamAll(), [
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 1],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'position' => 2],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'position' => 3],
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 4],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 5],
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 6],
        ]);
    }

    public function test_read_returns_all_events_after_first_event_was_accessed(): void
    {
        $this->appendDummyEvents();
        $eventStream = $this->streamAll();
        $firstEvent = $eventStream->first();
        self::assertInstanceOf(SequencedEvent::class, $firstEvent);
        self::assertEquals(['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 1], self::sequencedEventToArray(['type', 'data', 'tags', 'position'], $firstEvent));
        self::assertEventStream($eventStream, [
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 1],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'position' => 2],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'position' => 3],
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 4],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 5],
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 6],
        ]);
    }

    public function test_read_returns_allows_to_access_first_event_after_iteration(): void
    {
        $this->appendDummyEvents();
        $eventStream = $this->streamAll();
        self::assertEventStream($eventStream, [
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 1],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'position' => 2],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'position' => 3],
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 4],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 5],
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 6],
        ]);
        $firstEvent = $eventStream->first();
        self::assertInstanceOf(SequencedEvent::class, $firstEvent);
        self::assertEquals(['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 1], self::sequencedEventToArray(['type', 'data', 'tags', 'position'], $firstEvent));
    }

    public function test_read_allows_to_specify_minimum_position(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->streamAll(ReadOptions::create(from: SequencePosition::fromInteger(4))), [
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 4],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 5],
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 6],
        ]);
    }

    public function test_read_allows_to_specify_limit(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->streamAll(ReadOptions::create(limit: 3)), [
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 1],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'position' => 2],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'position' => 3],
        ]);
    }

    public function test_read_returns_an_empty_stream_if_minimum_position_exceeds_highest(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->streamAll(ReadOptions::create(from: SequencePosition::fromInteger(123))), []);
    }

    public function test_read_allows_filtering_of_events_by_tag(): void
    {
        $this->appendDummyEvents();
        $query = Query::fromItems(QueryItem::create(tags: ['baz:foos']));
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
        $query = Query::fromItems(QueryItem::create(tags: ['foo:bar']), QueryItem::create(tags: ['baz:foos']));
        self::assertEventStream($this->stream($query), [
            ['position' => 1],
            ['position' => 2],
            ['position' => 3],
            ['position' => 4],
            ['position' => 6],
            ['position' => 7],
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
        $query = Query::fromItems(QueryItem::create(tags: ['foo:bar', 'baz:foos']));
        self::assertEventStream($this->stream($query), [
            ['position' => 2],
            ['position' => 3],
            ['position' => 7],
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
        $query = Query::fromItems(QueryItem::create(tags: ['foo:bar', 'baz:foos'], onlyLastEvent: true));
        self::assertEventStream($this->stream($query), [
            ['data' => 'g'],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_event_type(): void
    {
        $this->appendDummyEvents();
        $query = Query::fromItems(QueryItem::create(eventTypes: ['SomeEventType', 'SomeOtherEventType']));
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
        $query = Query::fromItems(QueryItem::create(eventTypes: ['SomeEventType']));
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
        $query = Query::fromItems(QueryItem::create(eventTypes: ['SomeEventType'], onlyLastEvent: true));
        self::assertEventStream($this->stream($query), [
            ['data' => 'e'],
        ]);
    }

    public function test_read_allows_filtering_of_events_by_tags_and_event_types(): void
    {
        $this->appendDummyEvents();
        $query = Query::fromItems(QueryItem::create(eventTypes: 'SomeEventType', tags: 'baz:foos'));
        self::assertEventStream($this->stream($query), [
            ['data' => 'a'],
            ['data' => 'e'],
        ]);
    }

    #[Group('feature_onlyLastEvent')]
    public function test_read_allows_filtering_of_last_event_by_tags_and_event_types(): void
    {
        $this->appendDummyEvents();
        $query = Query::fromItems(QueryItem::create(eventTypes: 'SomeEventType', tags: 'baz:foos', onlyLastEvent: true));
        self::assertEventStream($this->stream($query), [
            ['position' => 5, 'data' => 'e'],
        ]);
    }

    #[Group('feature_onlyLastEvent')]
    #[Group('feature_onlyLastEventCombined')]
    public function test_read_allows_filtering_of_last_events_by_tags_and_event_types_with_multiple_query_items(): void
    {
        $this->appendDummyEvents();
        $query = Query::fromItems(QueryItem::create(eventTypes: 'SomeEventType', tags: 'baz:foos', onlyLastEvent: true), QueryItem::create(eventTypes: 'SomeOtherEventType', tags: 'foo:bar', onlyLastEvent: true));
        self::assertEventStream($this->stream($query), [
            ['position' => 5, 'data' => 'e'],
            ['position' => 6, 'data' => 'f'],
        ]);
    }

    public function test_read_allows_fetching_no_events(): void
    {
        $this->appendDummyEvents();
        $query = Query::fromItems(QueryItem::create(eventTypes: ['NonExistingEventType']));
        self::assertEventStream($this->stream($query), []);
    }

    #[Group('feature_liveStream')]
    public function test_read_includes_events_that_where_appended_after_iteration_started(): void
    {
        $this->appendDummyEvents();
        $expectedEvents = [
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 1],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'position' => 2],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'position' => 3],
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 4],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 5],
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 6],
            ['data' => 'g', 'type' => 'SomeEventType', 'tags' => ['foo:bar', 'foo:baz'], 'position' => 7],
            ['data' => 'h', 'type' => 'SomeOtherEventType', 'tags' => ['bar:baz', 'foo:foos'], 'position' => 8],
        ];
        $actualEvents = [];
        $index = 0;
        foreach ($this->streamAll() as $sequencedEvent) {
            $actualEvents[] = self::sequencedEventToArray(isset($expectedEvents[$index]) ? array_keys($expectedEvents[$index]) : ['type', 'data', 'tags', 'position'], $sequencedEvent);
            if ($sequencedEvent->position->value === 3) {
                $this->appendEvents([
                    ['data' => 'g', 'type' => 'SomeEventType', 'tags' => ['foo:bar', 'foo:baz']],
                    ['data' => 'h', 'type' => 'SomeOtherEventType', 'tags' => ['foo:foos', 'bar:baz']],
                ]);
            }
            $index++;
        }

        self::assertEquals($expectedEvents, $actualEvents);
    }

    #[Group('feature_readBackwards')]
    public function test_read_backwards_returns_all_events_in_descending_order(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->streamAll(ReadOptions::create(backwards: true)), [
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 6],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 5],
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 4],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'position' => 3],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'position' => 2],
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 1],
        ]);
    }

    #[Group('feature_readBackwards')]
    public function test_read_backwards_allows_to_specify_maximum_position(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->streamAll(ReadOptions::create(from: SequencePosition::fromInteger(4), backwards: true)), [
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 4],
            ['data' => 'c', 'type' => 'SomeEventType', 'tags' => ['foo:bar'], 'position' => 3],
            ['data' => 'b', 'type' => 'SomeOtherEventType', 'tags' => ['foo:bar'], 'position' => 2],
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 1],
        ]);
    }

    #[Group('feature_readBackwards')]
    public function test_read_backwards_allows_to_specify_limit(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->streamAll(ReadOptions::create(limit: 3, backwards: true)), [
            ['data' => 'f', 'type' => 'SomeOtherEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 6],
            ['data' => 'e', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 5],
            ['data' => 'd', 'type' => 'SomeThirdEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 4],
        ]);
    }

    #[Group('feature_readBackwards')]
    public function test_read_backwards_returns_single_event_if_maximum_position_is_one(): void
    {
        $this->appendDummyEvents();
        self::assertEventStream($this->streamAll(ReadOptions::create(from: SequencePosition::fromInteger(1), backwards: true)), [
            ['data' => 'a', 'type' => 'SomeEventType', 'tags' => ['baz:foos', 'foo:bar'], 'position' => 1],
        ]);
    }

    #[Group('feature_onlyLastEvent')]
    #[Group('feature_onlyLastEventCombined')]
    #[Group('feature_readBackwards')]
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
        $query = Query::fromItems(QueryItem::create(tags: ['foo:bar'], onlyLastEvent: true), QueryItem::create(eventTypes: ['Type2', 'Type1'], tags: ['foo:bar']));

        /** @var SequencedEventShape[] $expectedEvents */
        $expectedEvents = [
            ['data' => 'a', 'position' => 1],
            ['data' => 'b', 'position' => 2],
            ['data' => 'f', 'position' => 6],
            ['data' => 'g', 'position' => 7],
        ];
        self::assertEventStream($this->stream($query), $expectedEvents);

        self::assertEventStream($this->stream($query, ReadOptions::create(backwards: true)), array_reverse($expectedEvents));
        self::assertEventStream($this->stream($query, ReadOptions::create(from: 3)), array_slice($expectedEvents, 2));
        self::assertEventStream($this->stream($query, ReadOptions::create(from: 3, backwards: true)), array_slice(array_reverse($expectedEvents), 2));
    }

    #[Group('feature_readBackwards')]
    public function test_append_appends_event_if_expected_highest_sequence_position_matches(): void
    {
        $this->appendDummyEvents();

        $query = Query::fromItems(QueryItem::create(eventTypes: 'SomeEventType', tags: 'baz:foos'));
        $lastEvent = $this->stream($query, ReadOptions::create(backwards: true))->first();
        self::assertInstanceOf(SequencedEvent::class, $lastEvent);
        $this->conditionalAppendEvent(['type' => 'SomeEventType', 'data' => 'new event', 'tags' => ['baz:foos']], $query, $lastEvent->position);

        self::assertEventStream($this->stream($query), [
            ['data' => 'a'],
            ['data' => 'e'],
            ['data' => 'new event'],
        ]);
    }

    #[Group('feature_appendConditionWithoutMatchingEvent')]
    public function test_append_appends_event_if_expected_highest_sequence_position_matches_no_events(): void
    {
        $query = Query::fromItems(QueryItem::create(eventTypes: 'SomeEventTypeThatDidNotOccur', tags: 'baz:foos'));
        $this->conditionalAppendEvent(['type' => 'SomeEventType', 'data' => 'new event'], $query, SequencePosition::fromInteger(123));
        self::assertEventStream($this->streamAll(), [
            ['data' => 'new event'],
        ]);
    }

    #[Group('feature_readBackwards')]
    public function test_append_fails_if_new_events_match_the_specified_query(): void
    {
        $this->appendDummyEvents();

        $query = Query::fromItems(QueryItem::create(tags: 'baz:foos'));
        $lastEvent = $this->stream($query, ReadOptions::create(backwards: true))->first();
        self::assertInstanceOf(SequencedEvent::class, $lastEvent);

        $this->appendEvent(['type' => 'SomeEventType', 'tags' => ['baz:foos']]);

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query, $lastEvent->position);
    }

    public function test_append_fails_if_no_highest_sequence_position_was_expected_but_query_matches_events(): void
    {
        $this->appendDummyEvents();

        $query = Query::fromItems(QueryItem::create(eventTypes: 'SomeEventType', tags: 'baz:foos'));

        $this->expectException(ConditionalAppendFailed::class);
        $this->conditionalAppendEvent(['type' => 'DoesNotMatter'], $query);
    }

    #[Group('feature_metadata')]
    public function test_add_and_read_metadata(): void
    {
        $this->appendEvent(['metadata' => ['foo' => 'bar', 'baz' => 'FOOS']]);
        self::assertEventStream($this->streamAll(), [
            ['metadata' => ['foo' => 'bar', 'baz' => 'FOOS'], 'position' => 1],
        ]);
    }

    #[Group('feature_recordedAt')]
    public function test_appends_sets_recordedAt_timestamp(): void
    {
        $this->now = new DateTimeImmutable('@123456789');
        $expectedRecordedAt = $this->now;
        $this->appendEvent([]);
        $this->now = new DateTimeImmutable('@987654321');
        self::assertEventStream($this->streamAll(), [
            ['recordedAt' => $expectedRecordedAt, 'position' => 1],
        ]);
    }

    // --- Helpers ---

    final protected function streamAll(ReadOptions|null $options = null): SequencedEvents
    {
        return $this->getEventStore()->read(Query::all(), $options);
    }

    final protected function stream(Query $query, ReadOptions|null $options = null): SequencedEvents
    {
        return $this->getEventStore()->read($query, $options);
    }

    final protected function appendDummyEvents(): void
    {
        $this->appendEvents(array_map(static fn($char) => [
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
     * @phpstan-param EventShape[] $events
     */
    final protected function appendEvents(array $events): void
    {
        $this->getEventStore()->append(Events::fromArray(array_map(self::arrayToEvent(...), $events)));
    }

    /**
     * @phpstan-param EventShape[] $events
     */
    final protected function conditionalAppendEvents(array $events, Query $query, SequencePosition|null $after = null): void
    {
        $this->getEventStore()->append(Events::fromArray(array_map(self::arrayToEvent(...), $events)), new AppendCondition($query, $after));
    }

    /**
     * @phpstan-param EventShape $event
     */
    final protected function conditionalAppendEvent(array $event, Query $query, SequencePosition|null $after = null): void
    {
        $this->conditionalAppendEvents([$event], $query, $after);
    }

    /**
     * @phpstan-param array<SequencedEventShape> $expectedEvents
     */
    final protected static function assertEventStream(SequencedEvents $sequencedEvents, array $expectedEvents): void
    {
        $actualEvents = [];
        $index = 0;
        foreach ($sequencedEvents as $sequencedEvent) {
            $actualEvents[] = self::sequencedEventToArray(isset($expectedEvents[$index]) ? array_keys($expectedEvents[$index]) : ['type', 'data', 'tags', 'position'], $sequencedEvent);
            $index++;
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
     * @phpstan-return SequencedEventShape
     */
    private static function sequencedEventToArray(array $keys, SequencedEvent $sequencedEvent): array
    {
        $supportedKeys = ['type', 'data', 'tags', 'position', 'metadata', 'recordedAt'];
        $unsupportedKeys = array_diff($keys, $supportedKeys);
        if ($unsupportedKeys !== []) {
            throw new InvalidArgumentException(sprintf('Invalid key(s) "%s" for expected event. Allowed keys are: "%s"', implode('", "', $unsupportedKeys), implode('", "', $supportedKeys)), 1684668588);
        }
        $actualAsArray = [
            'type' => $sequencedEvent->event->type->value,
            'data' => $sequencedEvent->event->data->value,
            'tags' => $sequencedEvent->event->tags->toStrings(),
            'metadata' => $sequencedEvent->event->metadata->value,
            'position' => $sequencedEvent->position->value,
            'recordedAt' => $sequencedEvent->recordedAt,
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

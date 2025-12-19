<?php

declare(strict_types=1);

namespace Unit\InMemoryEventStore;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Wwwision\DCBEventStore\AppendCondition\AppendCondition;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\Events;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\InMemoryEventStore\InMemoryEventStore;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;
use Wwwision\DCBEventStore\ReadOptions;
use Wwwision\DCBEventStore\SequencedEvent\SequencePosition;

#[CoversClass(InMemoryEventStore::class)]
#[Medium]
final class InMemoryEventStoreTest extends TestCase
{
    private ClockInterface $clock;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2024-01-15 12:00:00');
        $this->clock = new class ($this->now) implements ClockInterface {
            public function __construct(private DateTimeImmutable $now) {}
            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    public function test_create_without_clock_uses_default_clock(): void
    {
        $eventStore = InMemoryEventStore::create();
        $eventStore->append(Event::create('TestEvent', '{}'));

        $events = $eventStore->read(Query::all());
        $firstEvent = $events->first();

        self::assertNotNull($firstEvent);
        self::assertInstanceOf(DateTimeImmutable::class, $firstEvent->recordedAt);
    }

    public function test_create_with_clock_uses_provided_clock(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Event::create('TestEvent', '{}'));

        $events = $eventStore->read(Query::all());
        $firstEvent = $events->first();

        self::assertNotNull($firstEvent);
        self::assertEquals($this->now, $firstEvent->recordedAt);
    }

    public function test_read_returns_empty_when_no_events(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $events = $eventStore->read(Query::all());

        self::assertNull($events->first());
    }

    public function test_append_single_event_stores_event_with_sequence_position(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $event = Event::create('TestEvent', '{"key":"value"}');

        $eventStore->append($event);

        $events = $eventStore->read(Query::all());
        $sequencedEvent = $events->first();

        self::assertNotNull($sequencedEvent);
        self::assertEquals(SequencePosition::fromInteger(1), $sequencedEvent->position);
        self::assertEquals($this->now, $sequencedEvent->recordedAt);
        self::assertSame($event, $sequencedEvent->event);
    }

    public function test_append_multiple_events_stores_all_with_incrementing_positions(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $event1 = Event::create('Event1', '{"data":"1"}');
        $event2 = Event::create('Event2', '{"data":"2"}');
        $event3 = Event::create('Event3', '{"data":"3"}');

        $eventStore->append(Events::fromArray([$event1, $event2, $event3]));

        $events = iterator_to_array($eventStore->read(Query::all()));

        self::assertCount(3, $events);
        self::assertEquals(SequencePosition::fromInteger(1), $events[0]->position);
        self::assertEquals(SequencePosition::fromInteger(2), $events[1]->position);
        self::assertEquals(SequencePosition::fromInteger(3), $events[2]->position);
    }

    public function test_read_with_query_filters_by_event_type(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('TypeA', '{}'),
            Event::create('TypeB', '{}'),
            Event::create('TypeA', '{}'),
        ]));

        $query = Query::fromItems(QueryItem::create(eventTypes: 'TypeA'));
        $events = iterator_to_array($eventStore->read($query));

        self::assertCount(2, $events);
        self::assertSame('TypeA', $events[0]->event->type->value);
        self::assertSame('TypeA', $events[1]->event->type->value);
    }

    public function test_read_with_query_filters_by_tags(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('Event1', '{}', 'user:123'),
            Event::create('Event2', '{}', 'user:456'),
            Event::create('Event3', '{}', 'user:123'),
        ]));

        $query = Query::fromItems(QueryItem::create(tags: 'user:123'));
        $events = iterator_to_array($eventStore->read($query));

        self::assertCount(2, $events);
        self::assertEquals(SequencePosition::fromInteger(1), $events[0]->position);
        self::assertEquals(SequencePosition::fromInteger(3), $events[1]->position);
    }

    public function test_read_with_query_onlyLastEvent_returns_only_last_matching_event(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('TypeA', '{"index":"1"}'),
            Event::create('TypeA', '{"index":"2"}'),
            Event::create('TypeA', '{"index":"3"}'),
        ]));

        $query = Query::fromItems(QueryItem::create(eventTypes: 'TypeA', onlyLastEvent: true));
        $events = iterator_to_array($eventStore->read($query));

        self::assertCount(1, $events);
        self::assertEquals(SequencePosition::fromInteger(3), $events[0]->position);
    }

    public function test_read_with_backwards_option_returns_events_in_reverse_order(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('Event1', '{}'),
            Event::create('Event2', '{}'),
            Event::create('Event3', '{}'),
        ]));

        $events = iterator_to_array($eventStore->read(Query::all(), ReadOptions::create(backwards: true)));

        self::assertCount(3, $events);
        self::assertEquals(SequencePosition::fromInteger(3), $events[0]->position);
        self::assertEquals(SequencePosition::fromInteger(2), $events[1]->position);
        self::assertEquals(SequencePosition::fromInteger(1), $events[2]->position);
    }

    public function test_read_with_from_option_returns_events_from_position(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('Event1', '{}'),
            Event::create('Event2', '{}'),
            Event::create('Event3', '{}'),
            Event::create('Event4', '{}'),
        ]));

        $events = iterator_to_array($eventStore->read(Query::all(), ReadOptions::create(from: 2)));

        self::assertCount(3, $events);
        self::assertEquals(SequencePosition::fromInteger(2), $events[0]->position);
        self::assertEquals(SequencePosition::fromInteger(3), $events[1]->position);
        self::assertEquals(SequencePosition::fromInteger(4), $events[2]->position);
    }

    public function test_read_with_from_option_backwards_returns_events_from_position_backwards(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('Event1', '{}'),
            Event::create('Event2', '{}'),
            Event::create('Event3', '{}'),
            Event::create('Event4', '{}'),
        ]));

        $events = iterator_to_array($eventStore->read(Query::all(), ReadOptions::create(from: 3, backwards: true)));

        self::assertCount(3, $events);
        self::assertEquals(SequencePosition::fromInteger(3), $events[0]->position);
        self::assertEquals(SequencePosition::fromInteger(2), $events[1]->position);
        self::assertEquals(SequencePosition::fromInteger(1), $events[2]->position);
    }

    public function test_read_with_limit_option_returns_limited_events(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('Event1', '{}'),
            Event::create('Event2', '{}'),
            Event::create('Event3', '{}'),
            Event::create('Event4', '{}'),
        ]));

        $events = iterator_to_array($eventStore->read(Query::all(), ReadOptions::create(limit: 2)));

        self::assertCount(2, $events);
        self::assertEquals(SequencePosition::fromInteger(1), $events[0]->position);
        self::assertEquals(SequencePosition::fromInteger(2), $events[1]->position);
    }

    public function test_read_with_combined_options_applies_all_filters(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('Event1', '{}'),
            Event::create('Event2', '{}'),
            Event::create('Event3', '{}'),
            Event::create('Event4', '{}'),
        ]));

        $events = iterator_to_array($eventStore->read(
            Query::all(),
            ReadOptions::create(from: 2, limit: 2, backwards: true),
        ));

        self::assertCount(2, $events);
        self::assertEquals(SequencePosition::fromInteger(2), $events[0]->position);
        self::assertEquals(SequencePosition::fromInteger(1), $events[1]->position);
    }

    public function test_append_with_condition_succeeds_when_no_matching_events_exist(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Event::create('Event1', '{}', 'user:123'));

        $condition = new AppendCondition(
            failIfEventsMatch: Query::fromItems(QueryItem::create(tags: 'user:456')),
            after: null,
        );

        $eventStore->append(Event::create('Event2', '{}'), $condition);

        $events = iterator_to_array($eventStore->read(Query::all()));
        self::assertCount(2, $events);
    }

    public function test_append_with_condition_fails_when_matching_events_exist(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Event::create('Event1', '{}', 'user:123'));

        $condition = new AppendCondition(
            failIfEventsMatch: Query::fromItems(QueryItem::create(tags: 'user:123')),
            after: null,
        );

        $this->expectException(ConditionalAppendFailed::class);
        $eventStore->append(Event::create('Event2', '{}'), $condition);
    }

    public function test_append_with_condition_after_succeeds_when_no_events_after_position(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('Event1', '{}', 'user:123'),
            Event::create('Event2', '{}', 'user:456'),
        ]));

        $condition = new AppendCondition(
            failIfEventsMatch: Query::fromItems(QueryItem::create(tags: 'user:123')),
            after: SequencePosition::fromInteger(1),
        );

        $eventStore->append(Event::create('Event3', '{}'), $condition);

        $events = iterator_to_array($eventStore->read(Query::all()));
        self::assertCount(3, $events);
    }

    public function test_append_with_condition_after_fails_when_events_exist_after_position(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('Event1', '{}', 'user:123'),
            Event::create('Event2', '{}', 'user:123'),
        ]));

        $condition = new AppendCondition(
            failIfEventsMatch: Query::fromItems(QueryItem::create(tags: 'user:123')),
            after: SequencePosition::fromInteger(1),
        );

        $this->expectException(ConditionalAppendFailed::class);
        $eventStore->append(Event::create('Event3', '{}'), $condition);
    }

    public function test_read_with_multiple_query_items_returns_matching_events(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Events::fromArray([
            Event::create('TypeA', '{}', 'tag1:value1'),
            Event::create('TypeB', '{}', 'tag2:value2'),
            Event::create('TypeA', '{}', 'tag3:value3'),
        ]));

        $query = Query::fromItems(
            QueryItem::create(eventTypes: 'TypeA'),
            QueryItem::create(tags: 'tag2:value2'),
        );
        $events = iterator_to_array($eventStore->read($query));

        self::assertCount(3, $events);
    }

    public function test_read_without_options_uses_default_options(): void
    {
        $eventStore = InMemoryEventStore::create($this->clock);
        $eventStore->append(Event::create('Event1', '{}'));

        $events = $eventStore->read(Query::all());

        self::assertNotNull($events->first());
    }
}

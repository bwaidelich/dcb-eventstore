<?php

declare(strict_types=1);

namespace Unit\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\EventData;
use Wwwision\DCBEventStore\Event\Events;

use function json_encode;

#[CoversClass(Events::class)]
#[Medium]
final class EventsTest extends TestCase
{
    public function test_fromArray_creates_instance_with_events(): void
    {
        $event1 = Event::create('Type1', EventData::fromString('{}'));
        $event2 = Event::create('Type2', EventData::fromString('{}'));

        $events = Events::fromArray([$event1, $event2]);

        self::assertCount(2, $events);
    }

    public function test_none_creates_empty_instance(): void
    {
        $events = Events::none();

        self::assertCount(0, $events);
    }

    public function test_getIterator_allows_iteration(): void
    {
        $event1 = Event::create('Type1', EventData::fromString('{}'));
        $event2 = Event::create('Type2', EventData::fromString('{}'));
        $events = Events::fromArray([$event1, $event2]);

        $iterated = [];
        foreach ($events as $event) {
            $iterated[] = $event;
        }

        self::assertSame([$event1, $event2], $iterated);
    }

    public function test_map_transforms_events(): void
    {
        $event1 = Event::create('Type1', EventData::fromString('{}'));
        $event2 = Event::create('Type2', EventData::fromString('{}'));
        $events = Events::fromArray([$event1, $event2]);

        $result = $events->map(fn(Event $e) => $e->type->value);

        self::assertSame(['Type1', 'Type2'], $result);
    }

    public function test_filter_returns_filtered_events(): void
    {
        $event1 = Event::create('Type1', EventData::fromString('{}'));
        $event2 = Event::create('Type2', EventData::fromString('{}'));
        $event3 = Event::create('Type1', EventData::fromString('{}'));
        $events = Events::fromArray([$event1, $event2, $event3]);

        $filtered = $events->filter(fn(Event $e) => $e->type->value === 'Type1');

        self::assertCount(2, $filtered);
    }

    public function test_append_with_single_event(): void
    {
        $event1 = Event::create('Type1', EventData::fromString('{}'));
        $event2 = Event::create('Type2', EventData::fromString('{}'));
        $events = Events::fromArray([$event1]);

        $appended = $events->append($event2);

        self::assertCount(1, $events);
        self::assertCount(2, $appended);
    }

    public function test_append_with_Events_instance(): void
    {
        $event1 = Event::create('Type1', EventData::fromString('{}'));
        $event2 = Event::create('Type2', EventData::fromString('{}'));
        $event3 = Event::create('Type3', EventData::fromString('{}'));
        $events1 = Events::fromArray([$event1]);
        $events2 = Events::fromArray([$event2, $event3]);

        $appended = $events1->append($events2);

        self::assertCount(1, $events1);
        self::assertCount(2, $events2);
        self::assertCount(3, $appended);
    }

    public function test_count_returns_number_of_events(): void
    {
        $event1 = Event::create('Type1', EventData::fromString('{}'));
        $event2 = Event::create('Type2', EventData::fromString('{}'));
        $events = Events::fromArray([$event1, $event2]);

        self::assertSame(2, $events->count());
    }

    public function test_jsonSerialize_returns_array_of_events(): void
    {
        $event1 = Event::create('Type1', EventData::fromString('{}'));
        $event2 = Event::create('Type2', EventData::fromString('{}'));
        $events = Events::fromArray([$event1, $event2]);

        $serialized = $events->jsonSerialize();

        self::assertCount(2, $serialized);
        self::assertSame($event1, $serialized[0]);
        self::assertSame($event2, $serialized[1]);
    }

    public function test_json_encode_works(): void
    {
        $event = Event::create('Type1', EventData::fromString('{"key":"value"}'));
        $events = Events::fromArray([$event]);

        $json = json_encode($events);

        self::assertIsString($json);
        self::assertStringContainsString('Type1', $json);
    }

    public function test_append_preserves_immutability(): void
    {
        $event1 = Event::create('Type1', EventData::fromString('{}'));
        $event2 = Event::create('Type2', EventData::fromString('{}'));
        $events = Events::fromArray([$event1]);

        $appended = $events->append($event2);

        self::assertNotSame($events, $appended);
    }

    public function test_filter_preserves_immutability(): void
    {
        $event1 = Event::create('Type1', EventData::fromString('{}'));
        $event2 = Event::create('Type2', EventData::fromString('{}'));
        $events = Events::fromArray([$event1, $event2]);

        $filtered = $events->filter(fn(Event $e) => $e->type->value === 'Type1');

        self::assertNotSame($events, $filtered);
        self::assertCount(2, $events);
    }
}

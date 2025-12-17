<?php

declare(strict_types=1);

namespace Unit\SequencedEvent;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\SequencePosition;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvent;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvents;

#[CoversClass(SequencedEvents::class)]
#[Medium]
final class SequencedEventsTest extends TestCase
{
    public function test_iteration_fails_if_generator_produces_wrong_class(): void
    {
        $sequencedEvents = SequencedEvents::create(static fn() => yield new \stdClass());
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array($sequencedEvents);
    }

    public function test_iteration_returns_all_events_in_stream(): void
    {
        $mockSequencedEvents = [
            new SequencedEvent(SequencePosition::fromInteger(1), new DateTimeImmutable(), Event::create('SomeEventType', 'some-data')),
            new SequencedEvent(SequencePosition::fromInteger(2), new DateTimeImmutable(), Event::create('SomeOtherEventType', 'some-other-data')),
            new SequencedEvent(SequencePosition::fromInteger(3), new DateTimeImmutable(), Event::create('SomeEventType', 'some-third-data')),
        ];
        $sequencedEvents = SequencedEvents::fromArray($mockSequencedEvents);
        self::assertSame($mockSequencedEvents, iterator_to_array($sequencedEvents));
    }

    public function test_first_fails_if_generator_produces_wrong_class(): void
    {
        $sequencedEvents = SequencedEvents::create(static fn() => yield new \stdClass());
        $this->expectException(InvalidArgumentException::class);
        $sequencedEvents->first();
    }

    public function test_first_returns_null_if_stream_is_empty(): void
    {
        $sequencedEvents = SequencedEvents::create(static fn() => yield from []);
        self::assertNull($sequencedEvents->first());
    }

    public function test_first_returns_first_event_in_stream(): void
    {
        $mockSequencedEvents = [
            new SequencedEvent(SequencePosition::fromInteger(1), new DateTimeImmutable(), Event::create('SomeEventType', 'some-data')),
            new SequencedEvent(SequencePosition::fromInteger(2), new DateTimeImmutable(), Event::create('SomeOtherEventType', 'some-other-data')),
            new SequencedEvent(SequencePosition::fromInteger(3), new DateTimeImmutable(), Event::create('SomeEventType', 'some-third-data')),
        ];
        $sequencedEvents = SequencedEvents::fromArray($mockSequencedEvents);
        self::assertSame($mockSequencedEvents[0], $sequencedEvents->first());
    }

    public function test_accessing_first_event_does_not_mutate_stream(): void
    {
        $mockSequencedEvents = [
            new SequencedEvent(SequencePosition::fromInteger(1), new DateTimeImmutable(), Event::create('SomeEventType', 'some-data')),
            new SequencedEvent(SequencePosition::fromInteger(2), new DateTimeImmutable(), Event::create('SomeOtherEventType', 'some-other-data')),
            new SequencedEvent(SequencePosition::fromInteger(3), new DateTimeImmutable(), Event::create('SomeEventType', 'some-third-data')),
        ];
        $sequencedEvents = SequencedEvents::fromArray($mockSequencedEvents);
        $sequencedEvents->first();
        self::assertSame($mockSequencedEvents, iterator_to_array($sequencedEvents));
    }

    public function test_iterating_stream_does_not_change_first_event(): void
    {
        $mockSequencedEvents = [
            new SequencedEvent(SequencePosition::fromInteger(1), new DateTimeImmutable(), Event::create('SomeEventType', 'some-data')),
            new SequencedEvent(SequencePosition::fromInteger(2), new DateTimeImmutable(), Event::create('SomeOtherEventType', 'some-other-data')),
            new SequencedEvent(SequencePosition::fromInteger(3), new DateTimeImmutable(), Event::create('SomeEventType', 'some-third-data')),
        ];
        $sequencedEvents = SequencedEvents::fromArray($mockSequencedEvents);
        iterator_to_array($sequencedEvents);
        self::assertSame($mockSequencedEvents[0], $sequencedEvents->first());
    }
}

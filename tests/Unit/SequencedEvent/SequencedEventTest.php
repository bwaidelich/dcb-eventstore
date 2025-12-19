<?php

declare(strict_types=1);

namespace Unit\SequencedEvent;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\EventData;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvent;
use Wwwision\DCBEventStore\SequencedEvent\SequencePosition;

#[CoversClass(SequencedEvent::class)]
#[Medium]
final class SequencedEventTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $position = SequencePosition::fromInteger(42);
        $recordedAt = new DateTimeImmutable('2024-01-15 10:30:00');
        $event = Event::create('SomeType', EventData::fromString('{}'));

        $sequencedEvent = new SequencedEvent($position, $recordedAt, $event);

        self::assertSame($position, $sequencedEvent->position);
        self::assertSame($recordedAt, $sequencedEvent->recordedAt);
        self::assertSame($event, $sequencedEvent->event);
    }

    public function test_properties_are_accessible(): void
    {
        $position = SequencePosition::fromInteger(1);
        $recordedAt = new DateTimeImmutable();
        $event = Event::create('TestType', EventData::fromString('{"test":true}'));

        $sequencedEvent = new SequencedEvent($position, $recordedAt, $event);

        self::assertEquals(1, $sequencedEvent->position->value);
        self::assertInstanceOf(DateTimeImmutable::class, $sequencedEvent->recordedAt);
        self::assertSame('TestType', $sequencedEvent->event->type->value);
    }
}

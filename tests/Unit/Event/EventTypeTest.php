<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Event\EventType;

#[Medium]
#[CoversClass(EventType::class)]
final class EventTypeTest extends TestCase
{
    public function test_equal_returns_true_if_event_types_match(): void
    {
        $eventType1 = EventType::fromString('some-type');
        $eventType2 = EventType::fromString('some-type');
        self::assertTrue($eventType1->equals($eventType2));
    }

    public function test_equal_returns_false_if_event_types_do_not_match(): void
    {
        $eventType1 = EventType::fromString('some-type');
        $eventType2 = EventType::fromString('Some-Type');
        self::assertFalse($eventType1->equals($eventType2));
    }

    public function test_jsonSerialize_returns_string_value(): void
    {
        $eventType = EventType::fromString('TestType');

        self::assertSame('TestType', $eventType->jsonSerialize());
    }
}

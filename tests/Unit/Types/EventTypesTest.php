<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Types;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Types\EventType;
use Wwwision\DCBEventStore\Types\EventTypes;

#[CoversClass(EventTypes::class)]
final class EventTypesTest extends TestCase
{

    public function test_must_not_be_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EventTypes::create();
    }

    public function test_create_orders_types(): void
    {
        $eventTypes = EventTypes::create(EventType::fromString('Foo'), EventType::fromString('Bar'), EventType::fromString('Baz'));
        self::assertSame(['Bar', 'Baz', 'Foo'], $eventTypes->toStringArray());
    }

    public function test_create_removes_duplicates(): void
    {
        $eventTypes = EventTypes::create(EventType::fromString('Foo'), EventType::fromString('Bar'), EventType::fromString('Foo'));
        self::assertSame(['Bar', 'Foo'], $eventTypes->toStringArray());
    }

    public function test_merge_removes_duplicates(): void
    {
        $eventTypes1 = EventTypes::create(EventType::fromString('Foo'), EventType::fromString('Bar'));
        $eventTypes2 = EventTypes::create(EventType::fromString('Foos'), EventType::fromString('Bar'), EventType::fromString('Baz'));
        self::assertSame(['Bar', 'Baz', 'Foo', 'Foos'], $eventTypes1->merge($eventTypes2)->toStringArray());
    }
}
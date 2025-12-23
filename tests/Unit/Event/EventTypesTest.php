<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Event\EventType;
use Wwwision\DCBEventStore\Event\EventTypes;

#[CoversClass(EventTypes::class)]
#[CoversClass(EventType::class)]
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

    public function test_single_creates_instance_with_single_event_type(): void
    {
        $eventTypes = EventTypes::single('Foo');
        self::assertSame(['Foo'], $eventTypes->toStringArray());
    }

    public function test_merge_removes_duplicates(): void
    {
        $eventTypes1 = EventTypes::create(EventType::fromString('Foo'), EventType::fromString('Bar'));
        $eventTypes2 = EventTypes::create(EventType::fromString('Foos'), EventType::fromString('Bar'), EventType::fromString('Baz'));
        self::assertSame(['Bar', 'Baz', 'Foo', 'Foos'], $eventTypes1->merge($eventTypes2)->toStringArray());
    }

    public function test_fromArray_accepts_EventType_instances(): void
    {
        $type1 = EventType::fromString('Type1');
        $type2 = EventType::fromString('Type2');
        $eventTypes = EventTypes::fromArray([$type1, $type2]);
        self::assertSame(['Type1', 'Type2'], $eventTypes->toStringArray());
    }

    public function test_fromStrings_creates_instance_from_strings(): void
    {
        $eventTypes = EventTypes::fromStrings('Type1', 'Type2', 'Type3');
        self::assertSame(['Type1', 'Type2', 'Type3'], $eventTypes->toStringArray());
    }

    public function test_contain_returns_true_if_type_exists(): void
    {
        $eventTypes = EventTypes::fromStrings('Type1', 'Type2');
        self::assertTrue($eventTypes->contain(EventType::fromString('Type1')));
    }

    public function test_contain_returns_false_if_type_does_not_exist(): void
    {
        $eventTypes = EventTypes::fromStrings('Type1', 'Type2');
        self::assertFalse($eventTypes->contain(EventType::fromString('Type3')));
    }

    public function test_getIterator_allows_iteration(): void
    {
        $eventTypes = EventTypes::fromStrings('Type1', 'Type2');
        $types = [];
        foreach ($eventTypes as $type) {
            $types[] = $type->value;
        }
        self::assertSame(['Type1', 'Type2'], $types);
    }

    public function test_jsonSerialize_returns_all_types(): void
    {
        $eventTypes = EventTypes::fromStrings('Type1', 'Type2');
        $serialized = json_encode($eventTypes, JSON_THROW_ON_ERROR);
        self::assertJsonStringEqualsJsonString('["Type1","Type2"]', $serialized);
    }

    public function test_merge_returns_same_instance_if_types_are_identical(): void
    {
        $eventTypes = EventTypes::fromStrings('Type1', 'Type2');
        $merged = $eventTypes->merge($eventTypes);
        self::assertSame($eventTypes, $merged);
    }
}

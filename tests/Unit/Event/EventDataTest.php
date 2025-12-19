<?php

declare(strict_types=1);

namespace Unit\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wwwision\DCBEventStore\Event\EventData;

#[Medium]
#[CoversClass(EventData::class)]
final class EventDataTest extends TestCase
{
    public function test_fromString_allows_empty_string(): void
    {
        $eventData = EventData::fromString('');
        self::assertSame('', $eventData->value);
    }

    public function test_jsonDecode_fails_if_data_is_no_valid_json(): void
    {
        $eventData = EventData::fromString('not json');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to JSON-decode event data: Syntax error');
        $eventData->jsonDecode();
    }

    public function test_jsonDecode_fails_if_data_is_no_json_array(): void
    {
        $eventData = EventData::fromString('true');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected an array. Got: boolean');
        $eventData->jsonDecode();
    }

    public function test_jsonDecode_returns_encoded_array_data(): void
    {
        $data = ['foo' => 'bar', 'bar' => ['baz' => true, 'foos' => 123.45]];
        $dataJson = json_encode($data, JSON_THROW_ON_ERROR);
        self::assertIsString($dataJson);
        $result = EventData::fromString($dataJson)->jsonDecode();
        self::assertSame($data, $result);
    }

    public function test_json_represents_value(): void
    {
        $eventData = EventData::fromString('input');
        self::assertSame('"input"', json_encode($eventData, JSON_THROW_ON_ERROR));
    }
}

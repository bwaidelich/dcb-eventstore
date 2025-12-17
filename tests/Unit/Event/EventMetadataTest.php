<?php

declare(strict_types=1);

namespace Unit\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Event\EventMetadata;

#[CoversClass(EventMetadata::class)]
final class EventMetadataTest extends TestCase
{
    public function test_none_creates_empty_instance(): void
    {
        self::assertSame([], EventMetadata::none()->value);
    }

    public function test_fromArray_fails_if_array_is_not_associative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EventMetadata::fromArray(['foo', 'bar']);
    }

    public function test_fromJson_fails_if_value_is_no_valid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EventMetadata::fromJson('no json');
    }

    public function test_fromJson_fails_if_value_is_no_json_object(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EventMetadata::fromJson('"no array"');
    }

    public function test_fromJson_fails_if_value_is_no_associative_json_object(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EventMetadata::fromJson('["foo", "bar"]');
    }

    public function test_with_sets_metadata_value(): void
    {
        self::assertSame(['foo' => 'bar'], EventMetadata::none()->with('foo', 'bar')->value);
    }

    public function test_with_overrides_previously_set_value(): void
    {
        self::assertSame(['foo' => 'replaced'], EventMetadata::none()->with('foo', 'bar')->with('foo', 'replaced')->value);
    }

    public function test_jsonSerializable(): void
    {
        $actualResult = json_encode(EventMetadata::none()->with('foo', 'bar')->with('bar', 'baz'), JSON_THROW_ON_ERROR);
        self::assertJsonStringEqualsJsonString('{"foo": "bar", "bar": "baz"}', $actualResult);
    }

}

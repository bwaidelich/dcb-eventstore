<?php

declare(strict_types=1);

namespace Unit\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\Tag;

#[Medium]
#[CoversClass(Event::class)]
final class EventTest extends TestCase
{
    public function test_create_fails_if_data_contains_invalid_json(): void
    {
        $this->expectExceptionMessage('Failed to JSON-encode event payload: Malformed UTF-8 characters, possibly incorrectly encoded');
        Event::create(type: 'SomeType', data: ["\xB1\x31"]);
    }

    public function test_create_fails_if_metadata_contains_invalid_json(): void
    {
        $this->expectExceptionMessage('Failed to decode JSON to event metadata: Syntax error');
        Event::create(type: 'SomeType', data: 'some-data', metadata: 'no-json');
    }

    /**
     * @return iterable<array<mixed>>
     */
    public static function dataProvider_create(): iterable
    {
        yield ['input' => ['type' => 'SomeEventType', 'data' => 'some-data'], 'expectedResult' => ['type' => 'SomeEventType', 'data' => 'some-data', 'tags' => [], 'metadata' => []]];
        yield ['input' => ['type' => 'SomeEventType', 'data' => ['some' => 'data']], 'expectedResult' => ['type' => 'SomeEventType', 'data' => '{"some":"data"}', 'tags' => [], 'metadata' => []]];
        yield ['input' => ['type' => 'SomeEventType', 'data' => 'some-data', 'tags' => Tag::fromString('some-tag')], 'expectedResult' => ['type' => 'SomeEventType', 'data' => 'some-data', 'tags' => ['some-tag'], 'metadata' => []]];
        yield ['input' => ['type' => 'SomeEventType', 'data' => 'some-data', 'tags' => 'some-tag'], 'expectedResult' => ['type' => 'SomeEventType', 'data' => 'some-data', 'tags' => ['some-tag'], 'metadata' => []]];
        yield ['input' => ['type' => 'SomeEventType', 'data' => 'some-data', 'tags' => ['some-tag', 'another-tag']], 'expectedResult' => ['type' => 'SomeEventType', 'data' => 'some-data', 'tags' => ['another-tag', 'some-tag'], 'metadata' => []]];
        yield ['input' => ['type' => 'SomeEventType', 'data' => 'some-data', 'metadata' => '{"foo":"bar"}'], 'expectedResult' => ['type' => 'SomeEventType', 'data' => 'some-data', 'tags' => [], 'metadata' => ['foo' => 'bar']]];
        yield ['input' => ['type' => 'SomeEventType', 'data' => 'some-data', 'metadata' => ['foo' => 'bar']], 'expectedResult' => ['type' => 'SomeEventType', 'data' => 'some-data', 'tags' => [], 'metadata' => ['foo' => 'bar']]];
    }

    /**
     * @param array<mixed> $input
     * @param array<mixed> $expectedResult
     */
    #[DataProvider('dataProvider_create')]
    public function test_create(array $input, array $expectedResult): void
    {
        /** @phpstan-ignore argument.type */
        $event = Event::create(...$input);
        $actualResult = [
            'type' => $event->type->value,
            'data' => $event->data->value,
            'tags' => $event->tags->toStrings(),
            'metadata' => $event->metadata->value,
        ];
        self::assertSame($expectedResult, $actualResult);
    }

    public function test_with_without_arguments(): void
    {
        $event = Event::create(type: 'SomeEventType', data: 'some-data');
        $event2 = $event->with();
        self::assertEquals($event, $event2);
    }

    public function test_with_with_string_tag(): void
    {
        $event = Event::create(type: 'SomeEventType', data: 'some-data')->with(tags: 'some-tag');
        self::assertSame(['some-tag'], $event->tags->toStrings());
    }

    public function test_with_with_array_tags(): void
    {
        $event = Event::create(type: 'SomeEventType', data: 'some-data')->with(tags: ['some-tag', 'another-tag']);
        self::assertSame(['another-tag', 'some-tag'], $event->tags->toStrings());
    }

    public function test_with_with_string_metadata(): void
    {
        $event = Event::create(type: 'SomeEventType', data: 'some-data')->with(metadata: '{"foo":"bar"}');
        self::assertSame(['foo' => 'bar'], $event->metadata->value);
    }

    public function test_with_with_array_metadata(): void
    {
        $event = Event::create(type: 'SomeEventType', data: 'some-data')->with(metadata: ['foo' => 'bar']);
        self::assertSame(['foo' => 'bar'], $event->metadata->value);
    }
}

<?php

declare(strict_types=1);

namespace Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\ReadOptions;
use Wwwision\DCBEventStore\SequencedEvent\SequencePosition;

#[CoversClass(ReadOptions::class)]
#[Medium]
final class ReadOptionsTest extends TestCase
{
    public function test_create_with_defaults(): void
    {
        $options = ReadOptions::create();

        self::assertNull($options->from);
        self::assertNull($options->limit);
        self::assertFalse($options->backwards);
    }

    public function test_create_with_SequencePosition(): void
    {
        $position = SequencePosition::fromInteger(42);
        $options = ReadOptions::create(from: $position);

        self::assertSame($position, $options->from);
        self::assertNull($options->limit);
        self::assertFalse($options->backwards);
    }

    public function test_create_with_integer_from(): void
    {
        $options = ReadOptions::create(from: 10);

        self::assertInstanceOf(SequencePosition::class, $options->from);
        self::assertEquals(10, $options->from->value);
    }

    public function test_create_with_limit(): void
    {
        $options = ReadOptions::create(limit: 100);

        self::assertNull($options->from);
        self::assertSame(100, $options->limit);
        self::assertFalse($options->backwards);
    }

    public function test_create_with_backwards_true(): void
    {
        $options = ReadOptions::create(backwards: true);

        self::assertNull($options->from);
        self::assertNull($options->limit);
        self::assertTrue($options->backwards);
    }

    public function test_create_with_backwards_false(): void
    {
        $options = ReadOptions::create(backwards: false);

        self::assertFalse($options->backwards);
    }

    public function test_create_with_all_parameters(): void
    {
        $position = SequencePosition::fromInteger(5);
        $options = ReadOptions::create(from: $position, limit: 50, backwards: true);

        self::assertSame($position, $options->from);
        self::assertSame(50, $options->limit);
        self::assertTrue($options->backwards);
    }

    public function test_backwards_property_is_mutable(): void
    {
        $options = ReadOptions::create();
        self::assertFalse($options->backwards);

        $options->backwards = true;
        self::assertTrue($options->backwards);
    }
}

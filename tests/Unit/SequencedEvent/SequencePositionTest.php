<?php

declare(strict_types=1);

namespace Unit\SequencedEvent;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\SequencedEvent\SequencePosition;

#[CoversClass(SequencePosition::class)]
#[Medium]
final class SequencePositionTest extends TestCase
{
    public function test_fromInteger_fails_for_negative_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sequence position has to be represented with a non-negative integer, given: -1');
        SequencePosition::fromInteger(-1);
    }

    public function test_previous_returns_previous_sequence_position(): void
    {
        $position = SequencePosition::fromInteger(10);
        self::assertSame(9, $position->previous()->value);
    }

    public function test_previous_fails_if_current_position_is_zero(): void
    {
        $position = SequencePosition::fromInteger(0);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('sequence position has to be represented with a non-negative integer, given: -1');
        $position->previous();
    }

    public function test_next_returns_next_sequence_position(): void
    {
        $position = SequencePosition::fromInteger(10);
        self::assertSame(11, $position->next()->value);
    }

    public function test_equals_returns_true_if_positions_match(): void
    {
        $position1 = SequencePosition::fromInteger(10);
        $position2 = SequencePosition::fromInteger(10);
        self::assertTrue($position1->equals($position2));
    }

    public function test_equals_returns_false_if_positions_dont_match(): void
    {
        $position1 = SequencePosition::fromInteger(10);
        $position2 = SequencePosition::fromInteger(11);
        self::assertFalse($position1->equals($position2));
    }
}

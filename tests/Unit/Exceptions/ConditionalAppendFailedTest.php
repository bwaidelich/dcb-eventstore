<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\SequencedEvent\SequencePosition;

#[CoversClass(ConditionalAppendFailed::class)]
#[Medium]
final class ConditionalAppendFailedTest extends TestCase
{
    public function test_becauseMatchingEventsExist_creates_exception_with_message(): void
    {
        $exception = ConditionalAppendFailed::becauseMatchingEventsExist();

        self::assertInstanceOf(ConditionalAppendFailed::class, $exception);
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('The event store contained events matching the specified query but none were expected', $exception->getMessage());
    }

    public function test_becauseMatchingEventsExistAfterSequencePosition_creates_exception_with_message(): void
    {
        $position = SequencePosition::fromInteger(42);

        $exception = ConditionalAppendFailed::becauseMatchingEventsExistAfterSequencePosition($position);

        self::assertInstanceOf(ConditionalAppendFailed::class, $exception);
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('The event store contained events matching the specified query after the highest expected sequence position of 42', $exception->getMessage());
    }

    public function test_exception_can_be_thrown_and_caught(): void
    {
        $this->expectException(ConditionalAppendFailed::class);
        $this->expectExceptionMessage('The event store contained events matching the specified query but none were expected');

        throw ConditionalAppendFailed::becauseMatchingEventsExist();
    }

    public function test_exception_with_position_can_be_thrown_and_caught(): void
    {
        $position = SequencePosition::fromInteger(100);

        $this->expectException(ConditionalAppendFailed::class);
        $this->expectExceptionMessage('The event store contained events matching the specified query after the highest expected sequence position of 100');

        throw ConditionalAppendFailed::becauseMatchingEventsExistAfterSequencePosition($position);
    }
}

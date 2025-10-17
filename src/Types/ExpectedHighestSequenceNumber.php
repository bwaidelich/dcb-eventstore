<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use RuntimeException;
use Wwwision\DCBEventStore\EventStore;

/**
 * The last expected {@see SequenceNumber} matching a {@see StreamQuery} for {@see EventStore::append()} calls
 * Note: {@see ExpectedHighestSequenceNumber::none()} is a special case that means that _no_ event must match the specified query
 * Note: {@see ExpectedHighestSequenceNumber::any()} is a special case that means that the events are appended without conditions
 */
final class ExpectedHighestSequenceNumber
{
    private function __construct(private readonly SequenceNumber|StreamState $sequenceNumber) {}

    /**
     * No event must match the specified query
     */
    public static function none(): self
    {
        return new self(StreamState::NONE);
    }

    public static function any(): self
    {
        return new self(StreamState::ANY);
    }

    public static function fromSequenceNumber(SequenceNumber $sequenceNumber): self
    {
        return new self($sequenceNumber);
    }

    public static function fromInteger(int $value): self
    {
        return $value === 0 ? self::none() : new self(SequenceNumber::fromInteger($value));
    }

    public function isNone(): bool
    {
        return $this->sequenceNumber === StreamState::NONE;
    }

    public function isAny(): bool
    {
        return $this->sequenceNumber === StreamState::ANY;
    }

    public function extractSequenceNumber(): SequenceNumber
    {
        if (!$this->sequenceNumber instanceof SequenceNumber) {
            throw new RuntimeException(sprintf('Failed to extract Sequence number from %s', $this), 1686747562);
        }
        return $this->sequenceNumber;
    }

    public function matches(SequenceNumber $sequenceNumber): bool
    {
        return $this->sequenceNumber instanceof SequenceNumber && $this->sequenceNumber->value >= $sequenceNumber->value;
    }

    public function __toString(): string
    {
        return $this->sequenceNumber instanceof StreamState ? '[' . $this->sequenceNumber->name . ']' : (string) $this->sequenceNumber->value;
    }
}

<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\SequencedEvent;

use Webmozart\Assert\Assert;

/**
 * The global sequence number of an event in the Event Store
 *
 * Note: The sequence position is usually not referred to in user land code, but it can be used to batch process an event stream for example
 */
final class SequencePosition
{
    private function __construct(public readonly int $value)
    {
        Assert::natural($this->value, 'sequence position has to be represented with a non-negative integer, given: %d');
    }

    public static function fromInteger(int $value): self
    {
        return new self($value);
    }

    public function previous(): self
    {
        return new self($this->value - 1);
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }
}

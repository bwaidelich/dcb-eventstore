<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use Webmozart\Assert\Assert;

/**
 * The global sequence number of an event in the Event Store
 *
 * Note: The sequence number is usually not referred to in user land code, but it can be used to batch process an event stream for example
 */
final readonly class SequenceNumber
{
    private function __construct(
        public int $value
    ) {
        Assert::natural($this->value, 'sequence number has to be a non-negative integer, given: %d');
    }

    public static function fromInteger(int $value): self
    {
        return new self($value);
    }

    public static function none(): self
    {
        return new self(0);
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

<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use JsonSerializable;
use Ramsey\Uuid\Uuid;
use Webmozart\Assert\Assert;

/**
 * Globally unique identifier of an event (usually formatted as UUID v4)
 */
final readonly class EventId implements JsonSerializable
{
    private function __construct(public string $value)
    {
        Assert::notEmpty($this->value);
    }

    public static function create(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}

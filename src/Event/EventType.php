<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Event;

use JsonSerializable;
use Webmozart\Assert\Assert;

/**
 * The type of event, e.g. "CustomerRenamed"
 */
final class EventType implements JsonSerializable
{
    public const int LENGTH_MAX = 255;

    private function __construct(public readonly string $value)
    {
        Assert::lengthBetween($this->value, 1, self::LENGTH_MAX);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}

<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use JsonSerializable;
use Webmozart\Assert\Assert;

/**
 * The type of an event, e.g. "CustomerRenamed"
 */
final readonly class EventType implements JsonSerializable
{
    private function __construct(public string $value)
    {
        Assert::notEmpty($this->value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}

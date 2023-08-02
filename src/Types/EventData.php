<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use JsonSerializable;

/**
 * String-based data of an event (usually the JSON-encoded payload of a domain event
 */
final readonly class EventData implements JsonSerializable
{
    private function __construct(
        public readonly string $value,
    ) {
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

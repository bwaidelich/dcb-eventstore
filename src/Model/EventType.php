<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use JsonSerializable;

final readonly class EventType implements JsonSerializable
{
    private function __construct(public string $value)
    {
        // TODO validate
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
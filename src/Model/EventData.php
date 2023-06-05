<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use JsonSerializable;

final readonly class EventData implements JsonSerializable
{
    private function __construct(
        public readonly string $value,
    ) {}

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
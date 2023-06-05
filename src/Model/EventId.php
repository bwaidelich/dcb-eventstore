<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use JsonSerializable;
use Ramsey\Uuid\Uuid;

final readonly class EventId implements JsonSerializable
{
    private function __construct(public string $value)
    {
        // TODO validate
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
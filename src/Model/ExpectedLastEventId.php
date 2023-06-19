<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use RuntimeException;
use Wwwision\DCBEventStore\EventStore;

/**
 * The last expected {@see EventId} matching a {@see StreamQuery} for {@see EventStore::conditionalAppend()} calls
 * Note: {@see ExpectedLastEventId::none()} is a special case that means that _no_ event must match the specified query
 */
final readonly class ExpectedLastEventId
{
    private function __construct(private ?string $value)
    {
    }

    /**
     * No event must match the specified query
     */
    public static function none(): self
    {
        return new self(null);
    }

    public static function fromEventId(EventId $eventId): self
    {
        return new self($eventId->value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function isNone(): bool
    {
        return $this->value === null;
    }

    public function eventId(): EventId
    {
        if ($this->value === null) {
            throw new RuntimeException('Failed to extract Event Id from ExpectedLastEventId[none]', 1686747562);
        }
        return EventId::fromString($this->value);
    }

    public function matches(EventId $eventId): bool
    {
        return $this->value === $eventId->value;
    }

    public function __toString(): string
    {
        return $this->value ?? '[NONE]';
    }
}

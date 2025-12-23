<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Event;

use JsonException;
use JsonSerializable;
use RuntimeException;
use Stringable;
use Webmozart\Assert\Assert;

/**
 * String-based data of an event (usually the JSON-encoded payload of a domain event
 */
final class EventData implements JsonSerializable, Stringable
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

    /**
     * @return array<mixed>
     */
    public function jsonDecode(): array
    {
        try {
            $result = json_decode($this->value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Failed to JSON-decode event data: {$e->getMessage()}", 1733231398, $e);
        }
        Assert::isArray($result);
        return $result;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

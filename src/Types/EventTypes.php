<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_values;
use function ksort;

/**
 * A type-safe set of {@see EventType} instances
 *
 * @implements IteratorAggregate<EventType>
 */
final class EventTypes implements IteratorAggregate, JsonSerializable
{
    /**
     * @param array<string, EventType> $types
     */
    private function __construct(
        public readonly array $types,
    ) {
        Assert::notEmpty($this->types, 'EventTypes must not be empty');
    }

    /**
     * @param array<mixed> $types
     */
    public static function fromArray(array $types): self
    {
        $convertedEventTypes = [];
        foreach ($types as $eventType) {
            if (!$eventType instanceof EventType) {
                Assert::string($eventType);
                $eventType = EventType::fromString($eventType);
            }
            $convertedEventTypes[$eventType->value] = $eventType;
        }
        ksort($convertedEventTypes);
        return new self($convertedEventTypes);
    }

    public static function create(EventType ...$types): self
    {
        return self::fromArray($types);
    }

    public static function fromStrings(string ...$types): self
    {
        return self::fromArray($types);
    }

    public static function single(string|EventType $type): self
    {
        return self::fromArray([$type]);
    }

    public function contain(EventType $type): bool
    {
        return array_key_exists($type->value, $this->types);
    }

    /**
     * @return string[]
     */
    public function toStringArray(): array
    {
        return array_keys($this->types);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->types);
    }

    public function merge(self $other): self
    {
        if ($this->types === $other->types) {
            return $this;
        }
        return self::fromArray(array_merge($this->types, $other->types));
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return array_values($this->types);
    }
}

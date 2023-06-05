<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

use function array_map;

/**
 * A type-safe set of {@see EventType} instances
 *
 * @implements IteratorAggregate<EventType>
 */
final class EventTypes implements IteratorAggregate
{
    /**
     * @param EventType[] $types
     */
    private function __construct(
        public readonly array $types,
    ) {
        //Assert::notEmpty($this->types, 'EventTypes must not be empty');
    }

    public static function create(EventType ...$types): self
    {
        return new self($types);
    }

    public static function none(): self
    {
        return new self([]);
    }

    public static function fromStrings(string ...$types): self
    {
        return new self(array_map(static fn (string $type) => EventType::fromString($type), $types));
    }

    public static function single(string|EventType $type): self
    {
        if (is_string($type)) {
            $type = EventType::fromString($type);
        }
        return self::create($type);
    }

    public function contains(EventType $type): bool
    {
        foreach ($this->types as $typesInSet) {
            if ($typesInSet->value === $type->value) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string[]
     */
    public function toStringArray(): array
    {
        return array_map(static fn (EventType $type) => $type->value, $this->types);
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
        return new self(array_merge($this->types, $other->types));
    }
}

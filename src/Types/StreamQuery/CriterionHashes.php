<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery;

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/**
 * A type-safe set of {@see CriterionHash} instances
 *
 * @implements IteratorAggregate<CriterionHash>
 */
final class CriterionHashes implements IteratorAggregate, JsonSerializable
{
    /**
     * @param array<string, CriterionHash> $hashesByValue
     */
    private function __construct(
        private readonly array $hashesByValue,
    ) {
    }

    /**
     * @param array<string|CriterionHash> $hashes
     */
    public static function fromArray(array $hashes): self
    {
        $hashesByValue = [];
        foreach ($hashes as $hash) {
            if (!$hash instanceof CriterionHash) {
                if (!is_string($hash)) {
                    throw new InvalidArgumentException(sprintf('Can only instantiate CriterionHashes from array of %s or string, given: %s', CriterionHash::class, get_debug_type($hash)), 1700918052);
                }
                $hash = CriterionHash::fromString($hash);
            }
            $hashesByValue[$hash->value] = $hash;
        }
        return new self($hashesByValue);
    }

    public static function create(CriterionHash ...$hashes): self
    {
        return self::fromArray($hashes);
    }

    public static function none(): self
    {
        return new self([]);
    }

    public function contain(CriterionHash $hash): bool
    {
        return array_key_exists($hash->value, $this->hashesByValue);
    }

    public function intersect(self $other): bool
    {
        return array_intersect_key($this->hashesByValue, $other->hashesByValue) !== [];
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->hashesByValue);
    }

    public function isEmpty(): bool
    {
        return $this->hashesByValue === [];
    }

    /**
     * @return string[]
     */
    public function toStringArray(): array
    {
        return array_values(array_map(static fn (CriterionHash $hash) => $hash->value, $this->hashesByValue));
    }

    /**
     * @return CriterionHash[]
     */
    public function jsonSerialize(): array
    {
        return $this->hashesByValue;
    }
}

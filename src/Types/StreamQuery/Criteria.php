<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

use function array_map;

/**
 * A type-safe set of {@see Criterion} instances
 *
 * @implements IteratorAggregate<Criterion>
 */
final class Criteria implements IteratorAggregate, JsonSerializable
{
    /**
     * @var Criterion[]
     */
    private readonly array $criteria;

    private function __construct(Criterion ...$criteria)
    {
        $this->criteria = $criteria;
    }

    /**
     * @param Criterion[] $criteria
     */
    public static function fromArray(array $criteria): self
    {
        return new self(...$criteria);
    }

    public static function create(Criterion ...$criteria): self
    {
        return new self(...$criteria);
    }

    public function with(Criterion $criterion): self
    {
        return new self(...[...$this->criteria, $criterion]);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->criteria);
    }

    public function isEmpty(): bool
    {
        return $this->criteria === [];
    }

    /**
     * @param Closure(Criterion $criterion): mixed $callback
     * @return array<string, mixed>
     */
    public function map(Closure $callback): array
    {
        return array_map($callback, $this->criteria);
    }

    /**
     * @return Criterion[]
     */
    public function jsonSerialize(): array
    {
        return $this->criteria;
    }
}

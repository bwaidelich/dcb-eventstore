<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery;

use Wwwision\DCBEventStore\Types\Tags;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventTypes;

/**
 * A Query describing events by their {@see Tags} and/or {@see EventTypes}
 */
final class StreamQuery
{
    public const VERSION = '1.0';

    private function __construct(
        public readonly Criteria $criteria,
    ) {
    }

    public static function create(Criteria $criteria): self
    {
        return new self($criteria);
    }

    public static function wildcard(): self
    {
        return new self(Criteria::fromArray([]));
    }

    public function withCriteria(Criteria $criteria): self
    {
        return new self($this->criteria->merge($criteria));
    }

    public function withCriterion(Criterion $criterion): self
    {
        return new self($this->criteria->with($criterion));
    }

    public function isWildcard(): bool
    {
        return $this->criteria->isEmpty();
    }
}

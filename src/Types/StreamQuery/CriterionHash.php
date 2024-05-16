<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery;

use JsonSerializable;
use Webmozart\Assert\Assert;

/**
 * An opaque hash of a {@see Criterion}
 */
final class CriterionHash implements JsonSerializable
{
    private function __construct(public readonly string $value)
    {
        Assert::notEmpty($this->value);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function fromParts(string ...$parts): self
    {
        return new self(md5(implode('|', $parts)));
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

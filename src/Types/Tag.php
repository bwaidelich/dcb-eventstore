<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use JsonSerializable;
use Webmozart\Assert\Assert;

/**
 * Tag that can be attached to an {@see Event}, usually containing some identifier for an entity or concept of the core domain, for example "product:sku123"
 */
final class Tag implements JsonSerializable
{
    public const int LENGTH_MAX = 150;

    private function __construct(
        public readonly string $value,
    ) {
        Assert::regex($value, '/^[[:alnum:]\-\_\:]{1,' . self::LENGTH_MAX . '}$/', 'tags must only contain alphanumeric characters, underscores, dashes and colons and must be between 1 and ' . self::LENGTH_MAX . ' characters long, given: %s');
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}

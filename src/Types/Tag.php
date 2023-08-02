<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use JsonSerializable;
use Webmozart\Assert\Assert;

use function explode;

/**
 * Tag that can be attached to an {@see Event}, usually containing some identifier for an entity or concept of the core domain
 */
final readonly class Tag implements JsonSerializable
{
    private function __construct(
        public string $key,
        public string $value,
    ) {
        Assert::regex($key, '/^[[:alnum:]\-\_]{1,50}$/', 'tag keys must only alphanumeric characters, underscores and dashes and must be between 1 and 50 characters long, given: %s');
        Assert::regex($value, '/^[[:alnum:]\-\_]{1,50}$/', 'tag values must only alphanumeric characters, underscores and dashes and must be between 1 and 50 characters long, given: %s');
    }

    public static function create(string $type, string $value): self
    {
        return new self($type, $value);
    }

    /**
     * @param array<mixed>|string $value
     * @return self
     */
    public static function parse(array|string $value): self
    {
        if (is_string($value)) {
            return self::fromString($value);
        }
        return self::fromArray($value);
    }

    public static function fromString(string $string): self
    {
        Assert::contains($string, ':');
        [$key, $value] = explode(':', $string);
        return new self($key, $value);
    }

    /**
     * @param array<mixed> $array
     */
    public static function fromArray(array $array): self
    {
        Assert::keyExists($array, 'key');
        Assert::string($array['key'], 'Tag key has to be of type string, given: %s');
        Assert::keyExists($array, 'value');
        Assert::string($array['value'], 'Tag value has to be of type string, given: %s');
        return new self($array['key'], $array['value']);
    }

    public function equals(self $other): bool
    {
        return $other->key === $this->key && $other->value === $this->value;
    }

    public function toString(): string
    {
        return $this->key . ':' . $this->value;
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}

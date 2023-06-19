<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use JsonSerializable;
use Webmozart\Assert\Assert;

use function array_merge;
use function in_array;
use function json_decode;

/**
 * A type-safe set of {@see DomainId} instances
 */
final readonly class DomainIds implements JsonSerializable
{
    /**
     * @param array<array<string, string>> $ids
     */
    private function __construct(private array $ids)
    {
        Assert::notEmpty($this->ids, 'DomainIds must not be empty');
    }

    public static function single(string $key, string $value): self
    {
        return new self([[$key => $value]]);
    }

    /**
     * @param array<DomainId>|array<array<string, string>> $ids
     */
    public static function fromArray(array $ids): self
    {
        $convertedIds = [];
        foreach ($ids as $keyAndValuePair) {
            if ($keyAndValuePair instanceof DomainId) {
                $keyAndValuePair = [$keyAndValuePair->key() => $keyAndValuePair->value()];
            }
            Assert::isArray($keyAndValuePair);
            if (in_array($keyAndValuePair, $convertedIds, true)) {
                continue;
            }
            $key = key($keyAndValuePair);
            Assert::string($key, 'domain ids only accepts keys of type string, given: %s');
            Assert::regex($key, '/^[a-z][a-zA-Z0-9_-]{0,19}$/', 'domain id keys must start with a lower case character, only contain alphanumeric characters, underscores and dashes and must not be longer than 20 characters, given: %s');
            $value = $keyAndValuePair[$key];
            Assert::string($value, 'domain ids only accepts values of type string, given: %s');
            Assert::regex($value, '/^[a-z][a-zA-Z0-9_-]{0,19}$/', 'domain id keys must start with a lower case character, only contain alphanumeric characters, underscores and dashes and must not be longer than 20 characters, given: %s');
            $convertedIds[] = [$key => $value];
        }
        usort($convertedIds, static fn(array $id1, array $id2) => strcasecmp(key($id1), key($id2)));
        return new self($convertedIds);
    }

    public static function fromJson(string $json): self
    {
        $domainIds = json_decode($json, true);
        Assert::isArray($domainIds, 'Failed to decode JSON to domain ids array');
        return self::fromArray($domainIds);
    }

    public static function create(DomainId ...$ids): self
    {
        return self::fromArray($ids);
    }

    public function merge(self|DomainId $other): self
    {
        if ($other instanceof DomainId) {
            $other = self::create($other);
        }
        if ($other->equals($this)) {
            return $this;
        }
        return self::fromArray(array_merge($this->ids, $other->ids));
    }

    public function contains(DomainId|string $key, string $value = null): bool
    {
        if ($key instanceof DomainId) {
            $value = $key->value();
            $key = $key->key();
        }
        Assert::notNull($value, 'contains() can be called with an instance of DomainId or $key and $value, but $value was not specified');
        return in_array([$key => $value], $this->ids, true);
    }

    public function intersects(self|DomainId $other): bool
    {
        if ($other instanceof DomainId) {
            $other = self::create($other);
        }
        foreach ($other->ids as $keyValuePair) {
            $key = (string)key($keyValuePair);
            $value = $keyValuePair[$key];
            if ($this->contains($key, $value)) {
                return true;
            }
        }
        return false;
    }

    public function equals(self $other): bool
    {
        return $this->ids === $other->ids;
    }

    /**
     * @return array<array<string, string>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return array<array<string, string>>
     */
    public function toArray(): array
    {
        return array_values($this->ids);
    }
}

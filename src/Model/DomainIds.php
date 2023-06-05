<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use Webmozart\Assert\Assert;

use function array_diff;
use function array_key_exists;
use function array_merge;
use function json_decode;

/**
 * A type-safe set of {@see DomainId} instances
 *
 * @implements IteratorAggregate<string, string>
 */
final readonly class DomainIds implements IteratorAggregate, JsonSerializable
{
    /**
     * @param array<string, string> $ids
     */
    private function __construct(private array $ids)
    {
    }

    public static function single(string $key, string $value): self
    {
        return new self([$key => $value]);
    }

    /**
     * @param array<DomainId>|array<string, string> $ids
     */
    public static function fromArray(array $ids): self
    {
        $convertedIds = [];
        foreach ($ids as $key => $value) {
            if ($value instanceof DomainId) {
                $key = $value->key();
                $value = $value->value();
            }
            Assert::string($key, 'domain ids only accepts keys of type string, given: %s');
            Assert::regex($key, '/^[a-z][a-z0-9_]{0,20}/', 'domain id values must be all lower case, only contain alphanumeric characters and underscores and must not be longer than 20 characters, given: %s');
            Assert::string($value, 'domain ids only accepts values of type string, given: %s');
            Assert::keyNotExists($convertedIds, $key, 'The domain id "%s" occurs multiple times, but every domain id key can only appear once');
            $convertedIds[$key] = $value;
        }
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

    public static function none(): self
    {
        return new self([]);
    }

    public function merge(self $other): self
    {
        if ($other->equals($this)) {
            return $this;
        }
        foreach ($this->ids as $key => $value) {
            if (array_key_exists($key, $other->ids) && $other->ids[$key] !== $value) {
                throw new InvalidArgumentException(sprintf('Failed to merge domain ids with different value for the same key "%s"', $key), 1685955675);
            }
        }
        return new self(array_merge($this->ids, $other->ids));
    }

    public function contains(DomainId|string $key, string $value = null): bool
    {
        if ($key instanceof DomainId) {
            $value = $key->value();
            $key = $key->key();
        }
        Assert::notNull($value, 'contains() can be called with an instance of DomainId or $key and $value, but $value was not specified');
        return isset($this->ids[$key]) && $this->ids[$key] === $value;
    }

    public function intersects(self $other): bool
    {
        foreach ($other->ids as $key => $value) {
            if ($this->contains($key, $value)) {
                return true;
            }
        }
        return false;
    }

    public function equals(self $other): bool
    {
        return array_diff($this->ids, $other->ids) === [];
    }

    public function getIterator(): Traversable
    {
        foreach ($this->ids as $key => $value) {
            yield $key => $value;
        }
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->ids;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->ids;
    }
}

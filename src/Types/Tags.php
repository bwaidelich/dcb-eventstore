<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use JsonException;
use JsonSerializable;
use Traversable;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function array_merge;
use function json_decode;

/**
 * A type-safe set of {@see Tag} and {@see TagUnion} instances
 *
 * @implements IteratorAggregate<Tag>
 */
final class Tags implements IteratorAggregate, JsonSerializable
{
    /**
     * @param array<string, TagUnion|Tag> $tags
     */
    private function __construct(private readonly array $tags)
    {
    }

    /**
     * @param array<TagUnion|Tag|string> $tags
     */
    public static function fromArray(array $tags): self
    {
        $convertedTags = [];
        foreach ($tags as $tag) {
            if ($tag instanceof TagUnion) {
                $convertedTags[$tag->toString()] = $tag;
                continue;
            }

            if (!$tag instanceof Tag) {
                if (!is_string($tag)) {
                    throw new InvalidArgumentException(sprintf('Tags must be of type %s or string, given: %s', Tag::class, get_debug_type($tag)), 1690808045);
                }
                $tag = Tag::fromString($tag);
            }
            $convertedTags[$tag->value] = $tag;
        }
        ksort($convertedTags);
        return new self($convertedTags);
    }

    public static function fromJson(string $json): self
    {
        try {
            $tags = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(sprintf('Failed to decode JSON to tags: %s', $e->getMessage()), 1690807603, $e);
        }
        Assert::isArray($tags, 'Failed to decode JSON to tags');
        return self::fromArray($tags);
    }

    public static function single(string $value): self
    {
        return self::fromArray([Tag::fromString($value)]);
    }

    public static function create(TagUnion|Tag ...$tags): self
    {
        return self::fromArray($tags);
    }

    public function merge(self|TagUnion|Tag $other): self
    {
        if (! $other instanceof self) {
            $other = self::create($other);
        }
        if ($other->equals($this)) {
            return $this;
        }
        return self::fromArray(array_merge($this->tags, $other->tags));
    }

    public function contain(TagUnion|Tag $tag): bool
    {
        return match (true) {
            $tag instanceof TagUnion => $this->intersect($tag),
            $tag instanceof Tag => array_key_exists($tag->value, $this->tags),
        };
    }

    public function containEvery(Tags|TagUnion $tags): bool
    {
        foreach ($tags as $tag) {
            if (!$this->contain($tag)) {
                return false;
            }
        }
        return true;
    }

    public function intersect(self|TagUnion|Tag $other): bool
    {
        if ($other instanceof Tag) {
            $other = self::create($other);
        }
        foreach ($other as $tag) {
            if ($this->contain($tag)) {
                return true;
            }
        }
        return false;
    }

    public function equals(self $other): bool
    {
        return array_keys($this->tags) === array_keys($other->tags);
    }

    /**
     * @return list<string> in the format ['someKey:someValue', 'someKey:someOtherValue']
     */
    public function toStrings(): array
    {
        $strings = [];

        foreach ($this->tags as $tag) {
            match (true) {
                $tag instanceof TagUnion => $strings = [...$strings, ...$tag->tags->toStrings()],
                $tag instanceof Tag => $strings[] = $tag->value,
            };
        }

        return $strings;
    }

    /**
     * @return array<TagUnion|Tag>
     */
    public function jsonSerialize(): array
    {
        return array_values($this->tags);
    }

    /**
     * @return Traversable<TagUnion|Tag>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tags);
    }
}

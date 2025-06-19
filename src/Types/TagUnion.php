<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use IteratorAggregate;
use JsonSerializable;
use Traversable;

/** @implements IteratorAggregate<self|Tag> */
final readonly class TagUnion implements IteratorAggregate, JsonSerializable
{
    public function __construct(public Tags $tags)
    {
    }

    public static function create(Tag|Tags ...$tags): self
    {
        $allTags = Tags::create();

        foreach ($tags as $tag) {
            $allTags = $allTags->merge($tag);
        }

        return new self($allTags);
    }

    public function toString(): string
    {
        return implode('|', $this->tags->toStrings());
    }

    /** @return array{union: Tags} */
    public function jsonSerialize(): array
    {
        return ['union' => $this->tags];
    }

    /**
     * @return Traversable<TagUnion|Tag>
     */
    public function getIterator(): Traversable
    {
        return $this->tags->getIterator();
    }
}

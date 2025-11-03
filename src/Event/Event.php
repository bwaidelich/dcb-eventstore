<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Event;

use InvalidArgumentException;
use JsonException;

/**
 * The raw low-level event that is stored in the Events Store
 */
final class Event
{
    private function __construct(
        public readonly EventType $type,
        public readonly EventData $data, // opaque, no size limit?
        public readonly Tags $tags,
        public readonly EventMetadata $metadata,
    ) {}

    /**
     * @param EventData|array<mixed>|string $data
     * @param Tags|Tag|array<Tag|string>|string|null $tags
     * @param EventMetadata|array<string,mixed>|string|null $metadata
     */
    public static function create(
        EventType|string $type,
        EventData|array|string $data,
        Tags|Tag|array|string|null $tags = null,
        EventMetadata|array|string|null $metadata = null,
    ): self {
        if (is_string($type)) {
            $type = EventType::fromString($type);
        }
        if (is_string($data)) {
            $data = EventData::fromString($data);
        } elseif (is_array($data)) {
            try {
                $dataJson = json_encode($data, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new InvalidArgumentException("Failed to JSON-encode event payload: {$e->getMessage()}", 1733231100, $e);
            }
            $data = EventData::fromString($dataJson);
        }
        if ($tags === null) {
            $tags = Tags::create();
        } elseif ($tags instanceof Tag) {
            $tags = Tags::create($tags);
        } elseif (is_string($tags)) {
            $tags = Tags::single($tags);
        } elseif (is_array($tags)) {
            $tags = Tags::fromArray($tags);
        }
        if ($metadata === null) {
            $metadata = EventMetadata::none();
        } elseif (is_string($metadata)) {
            $metadata = EventMetadata::fromJson($metadata);
        } elseif (is_array($metadata)) {
            $metadata = EventMetadata::fromArray($metadata);
        }
        return new self($type, $data, $tags, $metadata);
    }

    /**
     * @param Tags|array<Tag|string>|string|null $tags
     * @param EventMetadata|array<string,mixed>|string|null $metadata
     */
    public function with(
        Tags|array|string|null $tags = null,
        EventMetadata|array|string|null $metadata = null,
    ): self {
        if (is_string($tags)) {
            $tags = Tags::single($tags);
        } elseif (is_array($tags)) {
            $tags = Tags::fromArray($tags);
        }
        if (is_string($metadata)) {
            $metadata = EventMetadata::fromJson($metadata);
        } elseif (is_array($metadata)) {
            $metadata = EventMetadata::fromArray($metadata);
        }
        return new self($this->type, $this->data, $tags ?? $this->tags, $metadata ?? $this->metadata);
    }
}

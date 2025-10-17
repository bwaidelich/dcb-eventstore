<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Event;

use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use Webmozart\Assert\Assert;

/**
 * Array-based metadata of an event
 */
final class EventMetadata implements JsonSerializable
{
    /**
     * @param array<string, mixed> $value
     */
    private function __construct(
        public readonly array $value,
    ) {}

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param array<mixed> $value
     */
    public static function fromArray(array $value): self
    {
        Assert::isMap($value, 'EventMetadata must consist of an associative array with string keys');
        return new self($value);
    }

    public static function fromJson(string $json): self
    {
        try {
            $metadata = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(sprintf('Failed to decode JSON to event metadata: %s', $e->getMessage()), 1692197194, $e);
        }
        Assert::isArray($metadata, 'Failed to decode JSON to event metadata');
        Assert::isMap($metadata, 'EventMetadata must consist of an associative array with string keys');
        return self::fromArray($metadata);
    }

    public function with(string $key, mixed $value): self
    {
        return new self([...$this->value, $key => $value]);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->value;
    }
}

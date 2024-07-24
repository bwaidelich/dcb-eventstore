<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Webmozart\Assert\Assert;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use function json_decode;
use function json_encode;
use function sprintf;
use function strrpos;
use function substr;
use const JSON_PRETTY_PRINT;

final class StreamQuerySerializer
{
    private function __construct()
    {
    }

    public static function serialize(StreamQuery $streamQuery): string
    {
        $array = [
            'version' => StreamQuery::VERSION,
            'criteria' => array_map(self::serializeCriterion(...), iterator_to_array($streamQuery->criteria)),
        ];
        try {
            return json_encode($array, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(sprintf('Failed to serialize StreamQuery: %s', $e->getMessage()), 1687970471, $e);
        }
    }

    public static function unserialize(string $string): StreamQuery
    {
        try {
            $array = json_decode($string, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(sprintf('Failed to unserialize StreamQuery: %s', $e->getMessage()), 1687970501, $e);
        }
        Assert::isArray($array);
        Assert::keyExists($array, 'criteria');
        Assert::keyExists($array, 'version');
        Assert::eq($array['version'], StreamQuery::VERSION, 'Expected a version equal to %2$s. Got: %s');
        Assert::isArray($array['criteria']);
        return StreamQuery::create(Criteria::fromArray(array_map(self::unserializeCriterion(...), $array['criteria'])));
    }

    /**
     * @param Criterion $criterion
     * @return array{type: string, properties: array<string, mixed>}
     */
    private static function serializeCriterion(Criterion $criterion): array
    {
        return [
            'type' => substr(substr($criterion::class, 0, -9), strrpos($criterion::class, '\\') + 1),
            'hash' => $criterion->hash(),
            'properties' => array_filter(get_object_vars($criterion), static fn ($v) => $v !== null),
        ];
    }

    /**
     * @param array<mixed> $criterion
     */
    private static function unserializeCriterion(array $criterion): Criterion
    {
        Assert::keyExists($criterion, 'type');
        Assert::string($criterion['type']);
        /** @var class-string<Criterion> $criterionClassName */
        $criterionClassName = 'Wwwision\\DCBEventStore\\Types\\StreamQuery\\Criteria\\' . $criterion['type'] . 'Criterion';
        return match ($criterionClassName) {
            EventTypesAndTagsCriterion::class => self::unserializeEventTypesAndTagsCriterion($criterion['properties']),
            default => throw new RuntimeException(sprintf('Unsupported criterion type %s', $criterionClassName), 1687970877),
        };
    }

    /**
     * @param array<mixed> $properties
     */
    private static function unserializeEventTypesAndTagsCriterion(array $properties): EventTypesAndTagsCriterion
    {
        Assert::nullOrIsArray($properties['eventTypes'] ?? null);
        Assert::nullOrIsArray($properties['tags'] ?? null);
        return EventTypesAndTagsCriterion::create(
            eventTypes: $properties['eventTypes'] ?? null,
            tags: $properties['tags'] ?? null,
            onlyLastEvent: $properties['onlyLastEvent'] ?? null,
        );
    }
}

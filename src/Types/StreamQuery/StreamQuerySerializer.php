<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Webmozart\Assert\Assert;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\TagsCriterion;
use Wwwision\DCBEventStore\Types\Tags;

use function get_debug_type;
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
            'properties' => get_object_vars($criterion),
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
            EventTypesCriterion::class => self::unserializeEventTypesCriterion($criterion['properties']),
            TagsCriterion::class => self::unserializeTagsCriterion($criterion['properties']),
            default => throw new RuntimeException(sprintf('Unsupported criterion type %s', $criterionClassName), 1687970877),
        };
    }

    /**
     * @param array<mixed> $properties
     */
    private static function unserializeEventTypesAndTagsCriterion(array $properties): EventTypesAndTagsCriterion
    {
        Assert::keyExists($properties, 'eventTypes');
        Assert::isArray($properties['eventTypes']);
        Assert::keyExists($properties, 'tags');
        Assert::isArray($properties['tags']);
        return new EventTypesAndTagsCriterion(EventTypes::fromStrings(...$properties['eventTypes']), Tags::fromArray($properties['tags']));
    }

    /**
     * @param array<mixed> $properties
     */
    private static function unserializeEventTypesCriterion(array $properties): EventTypesCriterion
    {
        Assert::keyExists($properties, 'eventTypes');
        Assert::isArray($properties['eventTypes']);
        return new EventTypesCriterion(EventTypes::fromStrings(...$properties['eventTypes']));
    }

    /**
     * @param array<mixed> $properties
     */
    private static function unserializeTagsCriterion(array $properties): TagsCriterion
    {
        Assert::keyExists($properties, 'tags');
        Assert::isArray($properties['tags']);
        return new TagsCriterion(Tags::fromArray($properties['tags']));
    }
}

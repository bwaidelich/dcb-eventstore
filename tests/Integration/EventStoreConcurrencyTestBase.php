<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration;

use Closure;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webmozart\Assert\Assert;
use Wwwision\DCBEventStore\AppendCondition\AppendCondition;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\EventData;
use Wwwision\DCBEventStore\Event\EventMetadata;
use Wwwision\DCBEventStore\Event\Events;
use Wwwision\DCBEventStore\Event\EventType;
use Wwwision\DCBEventStore\Event\Tag;
use Wwwision\DCBEventStore\Event\Tags;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;
use Wwwision\DCBEventStore\ReadOptions;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvent;
use Wwwision\DCBEventStore\SequencedEvent\SequencePosition;

use function array_map;
use function array_rand;
use function array_slice;
use function count;
use function getmypid;
use function json_decode;
use function json_encode;
use function min;
use function random_int;
use function range;
use function shuffle;
use function sprintf;

use const JSON_THROW_ON_ERROR;

#[CoversNothing]
abstract class EventStoreConcurrencyTestBase extends TestCase
{
    abstract public static function prepare(): void;

    abstract public static function cleanup(): void;

    abstract protected static function createEventStore(): EventStore;

    /**
     * @return iterable<int[]>
     */
    public static function consistency_dataProvider(): iterable
    {
        for ($i = 0; $i < 40; $i++) {
            yield [$i];
        }
    }

    #[DataProvider('consistency_dataProvider')]
    #[Group('parallel')]
    public function test_consistency(int $process): void
    {
        $numberOfEventTypes = 3;
        $numberOfTagKeys = 2;
        $numberOfTagValues = 3;
        $numberOfTags = 2;
        $maxNumberOfEventsPerCommit = 5;
        $numberOfEventBatches = 10;

        /** @var EventType[] $eventTypes */
        $eventTypes = self::spawn($numberOfEventTypes, static fn(int $index) => EventType::fromString('Events' . $index));
        /** @var string[] $tagKeys */
        $tagKeys = self::spawn($numberOfTagKeys, static fn(int $index) => 'key' . $index);
        /** @var string[] $tagValues */
        $tagValues = self::spawn($numberOfTagValues, static fn(int $index) => 'value' . $index);
        $tags = [];
        foreach ($tagKeys as $key) {
            $tags[] = Tag::fromString($key . ':' . self::either(...$tagValues));
        }
        $queryCreators = [
            static fn() => Query::all(),
            static fn() => Query::fromItems(QueryItem::create(tags: self::some($numberOfTags, ...$tags))),
            static fn() => Query::fromItems(QueryItem::create(eventTypes: self::some($numberOfEventTypes, ...$eventTypes))),
            static fn() => Query::fromItems(QueryItem::create(eventTypes: self::some($numberOfEventTypes, ...$eventTypes), tags: self::some($numberOfTags, ...$tags))),
            static fn() => Query::fromItems(QueryItem::create(eventTypes: self::some(1, ...$eventTypes), tags: self::some(1, ...$tags)), QueryItem::create(eventTypes: self::some(1, ...$eventTypes), tags: self::some(1, ...$tags))),
        ];

        for ($eventBatch = 0; $eventBatch < $numberOfEventBatches; $eventBatch++) {
            $query = self::either(...$queryCreators)();
            $expectedHighestSequencePosition = $this->getExpectedHighestSequencePosition($query);

            $numberOfEvents = self::between(1, $maxNumberOfEventsPerCommit);
            $events = [];
            for ($i = 0; $i < $numberOfEvents; $i++) {
                $descriptor = $process . '(' . getmypid() . ') ' . $eventBatch . '.' . ($i + 1) . '/' . $numberOfEvents;
                $eventData = $i > 0 ? ['descriptor' => $descriptor] : ['query' => self::serializeQuery($query), 'expectedHighestSequencePosition' => $expectedHighestSequencePosition?->value, 'descriptor' => $descriptor];
                $events[] = Event::create(type: self::either(...$eventTypes), data: EventData::fromString(json_encode($eventData, JSON_THROW_ON_ERROR)), tags: Tags::create(...self::some($numberOfTags, ...$tags)), metadata: EventMetadata::none());
            }
            try {
                static::createEventStore()->append(Events::fromArray($events), AppendCondition::create($query, $expectedHighestSequencePosition));
            } catch (ConditionalAppendFailed $e) {
            }
        }
        $this->expectNotToPerformAssertions();
    }

    final public static function validateEvents(): void
    {
        /** @var SequencedEvent[] $processedSequencedEvents */
        $processedSequencedEvents = [];
        $lastSequencePosition = 0;
        foreach (static::createEventStore()->read(Query::all()) as $sequencedEvent) {
            $payload = json_decode($sequencedEvent->event->data->value, true, 512, JSON_THROW_ON_ERROR);
            Assert::isArray($payload);
            if (isset($payload['query'])) {
                Assert::string($payload['query']);
                $query = self::unserializeQuery($payload['query']);
            } else {
                $query = null;
            }

            $sequencePosition = $sequencedEvent->position->value;
            self::assertGreaterThan($lastSequencePosition, $sequencePosition, sprintf('Expected sequence position to be greater than the previous one (%d) but it is %d', $lastSequencePosition, $sequencePosition));
            $lastMatchedSequencePosition = null;
            foreach ($processedSequencedEvents as $processedEvent) {
                if ($query !== null && $query->matchesEvent($processedEvent->event)) {
                    $lastMatchedSequencePosition = $processedEvent->position;
                }
            }
            if ($query !== null) {
                Assert::keyExists($payload, 'expectedHighestSequencePosition');
                if ($payload['expectedHighestSequencePosition'] === null) {
                    if ($lastMatchedSequencePosition !== null) {
                        throw new RuntimeException(sprintf('Event at sequence position %d was appended with no expectedHighestSequencePosition but the event "%s" matches the corresponding query', $sequencePosition, $lastMatchedSequencePosition->value));
                    }
                } elseif ($lastMatchedSequencePosition === null) {
                    Assert::integer($payload['expectedHighestSequencePosition']);
                    throw new RuntimeException(sprintf('Events at sequence position %d was appended with expectedHighestSequencePosition %d but no event matches the corresponding query', $sequencePosition, $payload['expectedHighestSequencePosition']));
                } elseif ($lastMatchedSequencePosition->value !== $payload['expectedHighestSequencePosition']) {
                    Assert::integer($payload['expectedHighestSequencePosition']);
                    throw new RuntimeException(sprintf('Events at sequence position %d was appended with expectedHighestSequencePosition %d but the last event that matches the corresponding query is "%s"', $sequencePosition, $payload['expectedHighestSequencePosition'], $lastMatchedSequencePosition->value));
                }
            }
            $lastSequencePosition = $sequencePosition;
            $processedSequencedEvents[] = $sequencedEvent;
        }
    }

    // ----------------------------------------------

    public function getExpectedHighestSequencePosition(Query $query): SequencePosition|null
    {
        $lastSequencedEvent = static::createEventStore()->read($query, ReadOptions::create(backwards: true))->first();
        if ($lastSequencedEvent === null) {
            return null;
        }
        return $lastSequencedEvent->position;
    }

    /**
     * @template T
     * @param Closure(int): T $closure
     * @return array<T>
     */
    private static function spawn(int $number, \Closure $closure): array
    {
        return array_map($closure, range(1, $number));
    }

    /**
     * @template T
     * @param T ...$choices
     * @return T
     */
    private static function either(...$choices): mixed
    {
        return $choices[array_rand($choices)];
    }

    /**
     * @template T
     * @param T ...$choices
     * @return array<T>
     */
    private static function some(int $max, ...$choices): array
    {
        $amount = self::between(1, min($max, count($choices)));
        shuffle($choices);
        return array_slice($choices, 0, $amount);
    }

    private static function between(int $min, int $max): int
    {
        return random_int($min, $max);
    }

    private static function serializeQuery(Query $query): string
    {
        return json_encode($query, JSON_THROW_ON_ERROR);
    }

    private static function unserializeQuery(string $json): Query
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        Assert::isArray($decoded);
        if ($decoded === []) {
            return Query::all();
        }
        /** @var array<array{eventTypes?: string[], tags?: string[], onlyLastEvent?: bool}> $decoded */
        return Query::fromItems(...array_map(static fn(array $item) => QueryItem::create(...$item), $decoded));
    }
}

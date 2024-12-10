<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventData;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\EventMetadata;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\EventType;
use Wwwision\DCBEventStore\Types\ExpectedHighestSequenceNumber;
use Wwwision\DCBEventStore\Types\ReadOptions;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuerySerializer;
use Wwwision\DCBEventStore\Types\Tag;
use Wwwision\DCBEventStore\Types\Tags;

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

        $eventTypes = self::spawn($numberOfEventTypes, static fn (int $index) => EventType::fromString('Events' . $index));
        $tagKeys = self::spawn($numberOfTagKeys, static fn (int $index) => 'key' . $index);
        $tagValues = self::spawn($numberOfTagValues, static fn (int $index) => 'value' . $index);
        $tags = [];
        foreach ($tagKeys as $key) {
            $tags[] = Tag::fromString($key . ':' . self::either(...$tagValues));
        }
        $queryCreators = [
            static fn () => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(tags: self::some($numberOfTags, ...$tags)))),
            static fn () => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: self::some($numberOfEventTypes, ...$eventTypes)))),
            static fn () => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: self::some($numberOfEventTypes, ...$eventTypes), tags: self::some($numberOfTags, ...$tags)))),
            static fn () => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: self::some(1, ...$eventTypes), tags: self::some(1, ...$tags)), EventTypesAndTagsCriterion::create(eventTypes: self::some(1, ...$eventTypes), tags: self::some(1, ...$tags)))),
        ];

        for ($eventBatch = 0; $eventBatch < $numberOfEventBatches; $eventBatch ++) {
            $query = self::either(...$queryCreators)();
            $expectedHighestSequenceNumber = $this->getExpectedHighestSequenceNumber($query);

            $numberOfEvents = self::between(1, $maxNumberOfEventsPerCommit);
            $events = [];
            for ($i = 0; $i < $numberOfEvents; $i++) {
                $descriptor = $process . '(' . getmypid() . ') ' . $eventBatch . '.' . ($i + 1) . '/' . $numberOfEvents;
                $eventData = $i > 0 ? ['descriptor' => $descriptor] : ['query' => StreamQuerySerializer::serialize($query), 'expectedHighestSequenceNumber' => $expectedHighestSequenceNumber->isNone() ? null : $expectedHighestSequenceNumber->extractSequenceNumber()->value, 'descriptor' => $descriptor];
                $events[] = Event::create(type: self::either(...$eventTypes), data: EventData::fromString(json_encode($eventData, JSON_THROW_ON_ERROR)), tags: Tags::create(...self::some($numberOfTags, ...$tags)), metadata: EventMetadata::none());
            }
            try {
                static::createEventStore()->append(Events::fromArray($events), new AppendCondition($query, $expectedHighestSequenceNumber));
            } catch (ConditionalAppendFailed $e) {
            }
        }
        self::assertTrue(true);
    }

    public static function validateEvents(): void
    {
        /** @var EventEnvelope[] $processedEventEnvelopes */
        $processedEventEnvelopes = [];
        $lastSequenceNumber = 0;
        foreach (static::createEventStore()->read(StreamQuery::wildcard()) as $eventEnvelope) {
            $payload = json_decode($eventEnvelope->event->data->value, true, 512, JSON_THROW_ON_ERROR);
            $query = isset($payload['query']) ? StreamQuerySerializer::unserialize($payload['query']) : null;
            $sequenceNumber = $eventEnvelope->sequenceNumber->value;
            self::assertGreaterThan($lastSequenceNumber, $sequenceNumber, sprintf('Expected sequence number to be greater than the previous one (%d) but it is %d', $lastSequenceNumber, $sequenceNumber));
            $lastMatchedSequenceNumber = null;
            foreach ($processedEventEnvelopes as $processedEvent) {
                if ($query !== null && $query->matchesEvent($processedEvent->event)) {
                    $lastMatchedSequenceNumber = $processedEvent->sequenceNumber;
                }
            }
            if ($query !== null) {
                if ($payload['expectedHighestSequenceNumber'] === null) {
                    self::assertNull($lastMatchedSequenceNumber, sprintf('Event at sequence number %d was appended with no expectedHighestSequenceNumber but the event "%s" matches the corresponding query', $sequenceNumber, $lastMatchedSequenceNumber?->value));
                } elseif ($lastMatchedSequenceNumber === null) {
                    self::fail(sprintf('Events at sequence number %d was appended with expectedHighestSequenceNumber %d but no event matches the corresponding query', $sequenceNumber, $payload['expectedHighestSequenceNumber']));
                } else {
                    self::assertSame($lastMatchedSequenceNumber->value, $payload['expectedHighestSequenceNumber'], sprintf('Events at sequence number %d was appended with expectedHighestSequenceNumber %d but the last event that matches the corresponding query is "%s"', $sequenceNumber, $payload['expectedHighestSequenceNumber'], $lastMatchedSequenceNumber->value));
                }
            }
            $lastSequenceNumber = $sequenceNumber;
            $processedEventEnvelopes[] = $eventEnvelope;
        }
    }

    // ----------------------------------------------


    public function getExpectedHighestSequenceNumber(StreamQuery $query): ExpectedHighestSequenceNumber
    {
        $lastEventEnvelope = static::createEventStore()->read($query, ReadOptions::create(backwards: true))->first();
        if ($lastEventEnvelope === null) {
            return ExpectedHighestSequenceNumber::none();
        }
        return ExpectedHighestSequenceNumber::fromSequenceNumber($lastEventEnvelope->sequenceNumber);
    }

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
}
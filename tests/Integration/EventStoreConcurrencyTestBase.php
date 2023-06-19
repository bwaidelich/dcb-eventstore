<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Random\Randomizer;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Exception\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Model\DomainId;
use Wwwision\DCBEventStore\Model\DomainIds;
use Wwwision\DCBEventStore\Model\Event;
use Wwwision\DCBEventStore\Model\EventData;
use Wwwision\DCBEventStore\Model\EventEnvelope;
use Wwwision\DCBEventStore\Model\EventId;
use Wwwision\DCBEventStore\Model\Events;
use Wwwision\DCBEventStore\Model\EventType;
use Wwwision\DCBEventStore\Model\EventTypes;
use Wwwision\DCBEventStore\Model\ExpectedLastEventId;
use Wwwision\DCBEventStore\Model\StreamQuery;
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
        $numberOfEventTypes = 5;
        $numberOfDomainIdKeys = 3;
        $numberOfDomainIdValues = 3;
        $numberOfDomainIds = 7;
        $maxNumberOfEventsPerCommit = 3;
        $numberOfEventBatches = 30;

        $eventTypes = self::spawn($numberOfEventTypes, static fn (int $index) => EventType::fromString('Event' . $index));
        $domainIdKeys = self::spawn($numberOfDomainIdKeys, static fn (int $index) => 'key' . $index);
        $domainIdValues = self::spawn($numberOfDomainIdValues, static fn (int $index) => 'value' . $index);
        $domainIds = [];
        foreach ($domainIdKeys as $key) {
            $domainIds[] = self::mockDomainId($key, self::either(...$domainIdValues));
        }
        $queryCreators = [static fn () => StreamQuery::matchingIds(DomainIds::create(...self::some($numberOfDomainIds, ...$domainIds))), static fn () => StreamQuery::matchingTypes(EventTypes::create(...self::some($numberOfEventTypes, ...$eventTypes))), static fn () => StreamQuery::matchingIdsAndTypes(DomainIds::create(...self::some($numberOfDomainIds, ...$domainIds)), EventTypes::create(...self::some($numberOfEventTypes, ...$eventTypes))),];

        for ($eventBatch = 0; $eventBatch < $numberOfEventBatches; $eventBatch ++) {
            $query = self::either(...$queryCreators)();
            $expectedLastEventId = $this->getExpectedLastEventId($query);

            $numberOfEvents = self::between(1, $maxNumberOfEventsPerCommit);
            $events = [];
            for ($i = 0; $i < $numberOfEvents; $i++) {
                $descriptor = $process . '(' . getmypid() . ') ' . $eventBatch . '.' . ($i + 1) . '/' . $numberOfEvents;
                $eventData = $i > 0 ? ['descriptor' => $descriptor] : ['query' => self::streamQueryToArray($query), 'expectedLastEventId' => $expectedLastEventId->isNone() ? null : $expectedLastEventId->eventId()->value, 'descriptor' => $descriptor];
                $events[] = new Event(EventId::create(), self::either(...$eventTypes), EventData::fromString(json_encode($eventData, JSON_THROW_ON_ERROR)), DomainIds::create(...self::some($numberOfDomainIds, ...$domainIds)));
            }
            try {
                static::createEventStore()->conditionalAppend(Events::fromArray($events), $query, $expectedLastEventId);
            } catch (ConditionalAppendFailed $e) {
            }
        }
        self::assertTrue(true);
    }

    public static function validateEvents(): void
    {
        /** @var EventEnvelope[] $processedEvents */
        $processedEvents = [];
        $lastSequenceNumber = 0;
        foreach (static::createEventStore()->streamAll() as $eventEnvelope) {
            $payload = json_decode($eventEnvelope->event->data->value, true, 512, JSON_THROW_ON_ERROR);
            $query = isset($payload['query']) ? self::arrayToStreamQuery($payload['query']) : null;
            $sequenceNumber = $eventEnvelope->sequenceNumber->value;
            self::assertGreaterThan($lastSequenceNumber, $sequenceNumber, sprintf('Expected sequence number of event "%s" to be greater than the previous one (%d) but it is %d', $eventEnvelope->event->id->value, $lastSequenceNumber, $sequenceNumber));
            $eventId = $eventEnvelope->event->id->value;
            $lastMatchedEventId = null;
            foreach ($processedEvents as $processedEvent) {
                self::assertNotSame($eventId, $processedEvent->event->id->value, sprintf('Event id "%s" is used for events with sequence numbers %d and %d', $eventId, $processedEvent->sequenceNumber->value, $sequenceNumber));
                if ($query !== null && $query->matches($processedEvent->event)) {
                    $lastMatchedEventId = $processedEvent->event->id;
                }
            }
            if ($query !== null) {
                if ($payload['expectedLastEventId'] === null) {
                    self::assertNull($lastMatchedEventId, sprintf('Event "%s" (sequence number %d) was appended with no lastMatchedEventId but the event "%s" matches the corresponding query', $eventId, $sequenceNumber, $lastMatchedEventId?->value));
                } elseif ($lastMatchedEventId === null) {
                    self::fail(sprintf('Event "%s" (sequence number %d) was appended with lastMatchedEventId "%s" but no event matches the corresponding query', $eventId, $sequenceNumber, $payload['expectedLastEventId']));
                } else {
                    self::assertSame($lastMatchedEventId->value, $payload['expectedLastEventId'], sprintf('Event "%s" (sequence number %d) was appended with lastMatchedEventId "%s" but the last event that matches the corresponding query is "%s"', $eventId, $sequenceNumber, $payload['expectedLastEventId'], $lastMatchedEventId->value));
                }
            }
            $lastSequenceNumber = $sequenceNumber;
            $processedEvents[] = $eventEnvelope;
        }
    }

    // ----------------------------------------------

    private static function streamQueryToArray(StreamQuery $query): array
    {
        return ['domainIds' => $query->domainIds?->toArray(), 'types' => $query->types?->toStringArray(),];
    }

    private static function arrayToStreamQuery(array $array): StreamQuery
    {
        if ($array['domainIds'] !== null && $array['types'] !== null) {
            return StreamQuery::matchingIdsAndTypes(DomainIds::fromArray($array['domainIds']), EventTypes::fromStrings(...$array['types']));
        }
        if ($array['domainIds'] !== null) {
            return StreamQuery::matchingIds(DomainIds::fromArray($array['domainIds']));
        }
        return StreamQuery::matchingTypes(EventTypes::fromStrings(...$array['types']));
    }

    public function getExpectedLastEventId(StreamQuery $query): ExpectedLastEventId
    {
        $lastEventEnvelope = static::createEventStore()->stream($query)->last();
        if ($lastEventEnvelope === null) {
            return ExpectedLastEventId::none();
        }
        return ExpectedLastEventId::fromEventId($lastEventEnvelope->event->id);
    }

    private static function mockDomainId(string $key, string $value): DomainId
    {
        return new class ($key, $value) implements DomainId {
            public function __construct(private readonly string $key, private readonly string $value) {}

            public function key(): string
            {
                return $this->key;
            }

            public function value(): string
            {
                return $this->value;
            }
        };
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
        $randomizer = new Randomizer();
        $amount = self::between(1, min($max, count($choices)));
        $shuffledChoices = $randomizer->shuffleArray($choices);
        return array_slice($shuffledChoices, 0, $amount);
    }

    private static function between(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}
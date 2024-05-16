<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helpers;

use DateTimeImmutable;
use RuntimeException;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\ReadOptions;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\TagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criterion;
use Wwwision\DCBEventStore\Types\StreamQuery\CriterionHashes;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use function count;

/**
 * An in-memory implementation of the {@see EventStore} interface that mostly serves testing or debugging purposes
 *
 * NOTE: This implementation is not transaction-safe (and obviously not thread-safe), it should never be used in productive code!
 *
 * Usage:
 * $eventStore = InMemoryEventStore::create();
 * $eventStore->append($events);
 *
 * $inMemoryStream = $eventStore->stream($query);
 */
final class InMemoryEventStore implements EventStore
{
    /**
     * @var array<array{sequenceNumber:int,recordedAt:DateTimeImmutable,event:Event}>
     */
    private array $events = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function read(StreamQuery $query, ?ReadOptions $options = null): InMemoryEventStream
    {
        $options ??= ReadOptions::create();
        $matchingEventEnvelopes = [];
        $events = $this->events;
        if ($options->backwards) {
            $events = array_reverse($events);
        }
        foreach ($events as $event) {
            if ($options->from !== null && (($options->backwards && $event['sequenceNumber'] > $options->from->value) || (!$options->backwards && $event['sequenceNumber'] < $options->from->value))) {
                continue;
            }
            $matchedCriterionHashes = [];
            if (!$query->isWildcard()) {
                foreach ($query->criteria as $criterion) {
                    if (array_key_exists($criterion->hash()->value, $matchedCriterionHashes)) {
                        continue;
                    }
                    if (self::criterionMatchesEvent($criterion, $event['event'])) {
                        $matchedCriterionHashes[$criterion->hash()->value] = true;
                    }
                }
                if ($matchedCriterionHashes === []) {
                    continue;
                }
            }
            $matchingEventEnvelopes[] = new EventEnvelope(SequenceNumber::fromInteger($event['sequenceNumber']), $event['recordedAt'], CriterionHashes::fromArray(array_keys($matchedCriterionHashes)), $event['event']);
        }
        return InMemoryEventStream::create(...$matchingEventEnvelopes);
    }

    public function readAll(?ReadOptions $options = null): EventStream
    {
        return $this->read(StreamQuery::wildcard(), $options);
    }

    private static function criterionMatchesEvent(Criterion $criterion, Event $event): bool
    {
        return match ($criterion::class) {
            EventTypesAndTagsCriterion::class => $event->tags->containEvery($criterion->tags) && $criterion->eventTypes->contain($event->type),
            EventTypesCriterion::class => $criterion->eventTypes->contain($event->type),
            TagsCriterion::class => $event->tags->containEvery($criterion->tags),
            default => throw new RuntimeException(sprintf('The criterion type "%s" is not supported by the %s', $criterion::class, self::class), 1700302540),
        };
    }

    public function append(Events $events, AppendCondition $condition): void
    {
        if (!$condition->expectedHighestSequenceNumber->isAny()) {
            $lastEventEnvelope = $this->read($condition->query, ReadOptions::create(backwards: true))->first();
            if ($lastEventEnvelope === null) {
                if (!$condition->expectedHighestSequenceNumber->isNone()) {
                    throw ConditionalAppendFailed::becauseHighestExpectedSequenceNumberDoesNotMatch($condition->expectedHighestSequenceNumber);
                }
            } elseif ($condition->expectedHighestSequenceNumber->isNone()) {
                throw ConditionalAppendFailed::becauseNoEventWhereExpected();
            } elseif (!$condition->expectedHighestSequenceNumber->matches($lastEventEnvelope->sequenceNumber)) {
                throw ConditionalAppendFailed::becauseHighestExpectedSequenceNumberDoesNotMatch($condition->expectedHighestSequenceNumber);
            }
        }
        $sequenceNumber = count($this->events);
        foreach ($events as $event) {
            $sequenceNumber++;
            $this->events[] = [
                'sequenceNumber' => $sequenceNumber,
                'recordedAt' => new DateTimeImmutable(),
                'event' => $event,
            ];
        }
    }
}

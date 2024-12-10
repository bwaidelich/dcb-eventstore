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
use Wwwision\DCBEventStore\Types\EventEnvelopes;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\ReadOptions;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
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
    private EventEnvelopes $eventEnvelopes;

    private function __construct()
    {
        $this->eventEnvelopes = EventEnvelopes::none();
    }

    public static function create(): self
    {
        return new self();
    }

    public function read(StreamQuery $query, ?ReadOptions $options = null): InMemoryEventStream
    {
        $matchingCriterionHashesBySequenceNumber = [];
        $eventEnvelopes = $this->eventEnvelopes;
        foreach ($query->criteria as $criterion) {
            $onlyLastEvent = $criterion instanceof EventTypesAndTagsCriterion && $criterion->onlyLastEvent;
            if ($onlyLastEvent) {
                $eventEnvelopes = EventEnvelopes::fromArray(array_reverse(iterator_to_array($eventEnvelopes)));
            }
            foreach ($eventEnvelopes as $eventEnvelope) {
                if (!self::criterionMatchesEvent($criterion, $eventEnvelope->event)) {
                    continue;
                }
                $sequenceNumber = $eventEnvelope->sequenceNumber->value;
                if (!array_key_exists($sequenceNumber, $matchingCriterionHashesBySequenceNumber)) {
                    $matchingCriterionHashesBySequenceNumber[$sequenceNumber] = [];
                }
                $matchingCriterionHashesBySequenceNumber[$sequenceNumber][] = $criterion->hash();
                if ($onlyLastEvent) {
                    continue 2;
                }
            }
        }

        $matchingEventEnvelopes = [];
        $eventEnvelopes = $this->eventEnvelopes;
        $options ??= ReadOptions::create();
        if ($options->backwards) {
            $eventEnvelopes = EventEnvelopes::fromArray(array_reverse(iterator_to_array($eventEnvelopes)));
        }
        foreach ($eventEnvelopes as $eventEnvelope) {
            $sequenceNumber = $eventEnvelope->sequenceNumber->value;
            if ($options->from !== null && (($options->backwards && $sequenceNumber > $options->from->value) || (!$options->backwards && $sequenceNumber < $options->from->value))) {
                continue;
            }
            if (!array_key_exists($sequenceNumber, $matchingCriterionHashesBySequenceNumber) && !$query->isWildcard()) {
                continue;
            }

            $matchingEventEnvelopes[] = $eventEnvelope->withCriterionHashes(CriterionHashes::fromArray($matchingCriterionHashesBySequenceNumber[$sequenceNumber] ?? []));
        }
        return InMemoryEventStream::create(...$matchingEventEnvelopes);
    }

    private static function criterionMatchesEvent(Criterion $criterion, Event $event): bool
    {
        return match ($criterion::class) {
            EventTypesAndTagsCriterion::class => ($criterion->tags === null || $event->tags->containEvery($criterion->tags)) && ($criterion->eventTypes === null || $criterion->eventTypes->contain($event->type)),
            default => throw new RuntimeException(sprintf('The criterion type "%s" is not supported by the %s', $criterion::class, self::class), 1700302540),
        };
    }

    public function append(Events|Event $events, AppendCondition $condition): void
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
        $sequenceNumber = SequenceNumber::fromInteger(count($this->eventEnvelopes) + 1);
        $newEventEnvelopes = EventEnvelopes::none();
        if ($events instanceof Event) {
            $events = Events::fromArray([$events]);
        }
        foreach ($events as $event) {
            $newEventEnvelopes = $newEventEnvelopes->append(
                new EventEnvelope(
                    $sequenceNumber,
                    new DateTimeImmutable(),
                    CriterionHashes::none(),
                    $event,
                )
            );
            $sequenceNumber = $sequenceNumber->next();
        }
        $this->eventEnvelopes = $this->eventEnvelopes->append($newEventEnvelopes);
    }

    public function resetState(): void
    {
        $this->eventEnvelopes = EventEnvelopes::none();
    }
}

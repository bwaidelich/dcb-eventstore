<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helpers;

use DateTimeImmutable;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\SequenceNumber;
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
     * @var EventEnvelope[]
     */
    private array $eventEnvelopes = [];

    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    public function read(StreamQuery $query, ?SequenceNumber $from = null): InMemoryEventStream
    {
        $matchingEventEnvelopes = $this->eventEnvelopes;
        if ($from !== null) {
            $matchingEventEnvelopes = array_filter($matchingEventEnvelopes, static fn (EventEnvelope $eventEnvelope) => $eventEnvelope->sequenceNumber->value >= $from->value);
        }
        if (!$query->isWildcard()) {
            $matchingEventEnvelopes = array_filter($matchingEventEnvelopes, static fn (EventEnvelope $eventEnvelope) => $query->matches($eventEnvelope->event));
        }
        return InMemoryEventStream::create(...$matchingEventEnvelopes);
    }

    public function readBackwards(StreamQuery $query, ?SequenceNumber $from = null): InMemoryEventStream
    {
        $matchingEventEnvelopes = array_reverse($this->eventEnvelopes);
        if ($from !== null) {
            $matchingEventEnvelopes = array_filter($matchingEventEnvelopes, static fn (EventEnvelope $eventEnvelope) => $eventEnvelope->sequenceNumber->value <= $from->value);
        }
        if (!$query->isWildcard()) {
            $matchingEventEnvelopes = array_filter($matchingEventEnvelopes, static fn (EventEnvelope $eventEnvelope) => $query->matches($eventEnvelope->event));
        }
        return InMemoryEventStream::create(...$matchingEventEnvelopes);
    }

    public function append(Events $events, AppendCondition $condition): void
    {
        if (!$condition->expectedHighestSequenceNumber->isAny()) {
            $lastEventEnvelope = $this->readBackwards($condition->query)->first();
            if ($lastEventEnvelope === null) {
                if (!$condition->expectedHighestSequenceNumber->isNone()) {
                    throw ConditionalAppendFailed::becauseNoEventMatchedTheQuery($condition->expectedHighestSequenceNumber);
                }
            } elseif ($condition->expectedHighestSequenceNumber->isNone()) {
                throw ConditionalAppendFailed::becauseNoEventWhereExpected();
            } elseif (!$condition->expectedHighestSequenceNumber->matches($lastEventEnvelope->sequenceNumber)) {
                throw ConditionalAppendFailed::becauseHighestExpectedSequenceNumberDoesNotMatch($condition->expectedHighestSequenceNumber, $lastEventEnvelope->sequenceNumber);
            }
        }
        $sequenceNumber = count($this->eventEnvelopes);
        foreach ($events as $event) {
            $sequenceNumber++;
            $this->eventEnvelopes[] = new EventEnvelope(
                SequenceNumber::fromInteger($sequenceNumber),
                new DateTimeImmutable(),
                $event,
            );
        }
    }
}

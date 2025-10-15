<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helpers;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\EventEnvelopes;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\ReadOptions;
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
    private EventEnvelopes $eventEnvelopes;

    private function __construct(
        private readonly ClockInterface $clock,
    ) {
        $this->eventEnvelopes = EventEnvelopes::none();
    }

    public static function create(ClockInterface|null $clock = null): self
    {
        return new self($clock ?? new SystemClock());
    }

    public function read(StreamQuery $query, ?ReadOptions $options = null): InMemoryEventStream
    {
        $options ??= ReadOptions::create();

        if ($query->isWildcard()) {
            $eventEnvelopes = $this->eventEnvelopes;
        } else {
            /** @var array<int,EventEnvelope> $matchingEventEnvelopesBySequenceNumber */
            $matchingEventEnvelopesBySequenceNumber = [];
            foreach ($query->criteria as $criterion) {
                if ($criterion->onlyLastEvent) {
                    $eventEnvelopes = EventEnvelopes::fromArray(array_reverse(iterator_to_array($this->eventEnvelopes)));
                } else {
                    $eventEnvelopes = $this->eventEnvelopes;
                }
                foreach ($eventEnvelopes as $eventEnvelope) {
                    $sequenceNumber = $eventEnvelope->sequenceNumber->value;
                    if (!$criterion->matchesEvent($eventEnvelope->event)) {
                        continue;
                    }
                    $matchingEventEnvelopesBySequenceNumber[$sequenceNumber] = $eventEnvelope;
                    if ($criterion->onlyLastEvent) {
                        continue 2;
                    }
                }
            }
            ksort($matchingEventEnvelopesBySequenceNumber, SORT_NUMERIC);
            $eventEnvelopes = array_values($matchingEventEnvelopesBySequenceNumber);
        }
        if ($options->backwards) {
            $eventEnvelopes = EventEnvelopes::fromArray(array_reverse(iterator_to_array($eventEnvelopes)));
        }
        $matchingEventEnvelopes = [];
        foreach ($eventEnvelopes as $eventEnvelope) {
            $sequenceNumber = $eventEnvelope->sequenceNumber->value;
            if ($options->from !== null && (($options->backwards && $sequenceNumber > $options->from->value) || (!$options->backwards && $sequenceNumber < $options->from->value))) {
                continue;
            }
            $matchingEventEnvelopes[] = $eventEnvelope;
        }
        return InMemoryEventStream::create(...$matchingEventEnvelopes);
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
                    $this->clock->now(),
                    $event,
                )
            );
            $sequenceNumber = $sequenceNumber->next();
        }
        $this->eventEnvelopes = $this->eventEnvelopes->append($newEventEnvelopes);
    }
}

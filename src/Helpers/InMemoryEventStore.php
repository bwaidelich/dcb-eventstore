<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helpers;

use Psr\Clock\ClockInterface;
use Wwwision\DCBEventStore\AppendCondition\AppendCondition;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\Events;
use Wwwision\DCBEventStore\Event\SequencePosition;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\ReadOptions;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvent;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvents;

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
    private SequencedEvents $eventEnvelopes;

    private function __construct(
        private readonly ClockInterface $clock,
    ) {
        $this->eventEnvelopes = SequencedEvents::none();
    }

    public static function create(ClockInterface|null $clock = null): self
    {
        return new self($clock ?? new SystemClock());
    }

    public function read(Query $query, ReadOptions|null $options = null): InMemoryEventStream
    {
        $options ??= ReadOptions::create();

        if (!$query->hasItems()) {
            $eventEnvelopes = $this->eventEnvelopes;
        } else {
            /** @var array<int,SequencedEvent> $matchingEventEnvelopesBySequenceNumber */
            $matchingEventEnvelopesBySequenceNumber = [];
            foreach ($query as $queryItem) {
                if ($queryItem->onlyLastEvent) {
                    $eventEnvelopes = SequencedEvents::fromArray(array_reverse(iterator_to_array($this->eventEnvelopes)));
                } else {
                    $eventEnvelopes = $this->eventEnvelopes;
                }
                foreach ($eventEnvelopes as $eventEnvelope) {
                    $sequenceNumber = $eventEnvelope->position->value;
                    if (!$queryItem->matchesEvent($eventEnvelope->event)) {
                        continue;
                    }
                    $matchingEventEnvelopesBySequenceNumber[$sequenceNumber] = $eventEnvelope;
                    if ($queryItem->onlyLastEvent) {
                        continue 2;
                    }
                }
            }
            ksort($matchingEventEnvelopesBySequenceNumber, SORT_NUMERIC);
            $eventEnvelopes = array_values($matchingEventEnvelopesBySequenceNumber);
        }
        if ($options->backwards) {
            $eventEnvelopes = SequencedEvents::fromArray(array_reverse(iterator_to_array($eventEnvelopes)));
        }
        $matchingEventEnvelopes = [];
        foreach ($eventEnvelopes as $eventEnvelope) {
            $sequenceNumber = $eventEnvelope->position->value;
            if ($options->from !== null && (($options->backwards && $sequenceNumber > $options->from->value) || (!$options->backwards && $sequenceNumber < $options->from->value))) {
                continue;
            }
            $matchingEventEnvelopes[] = $eventEnvelope;
        }
        return InMemoryEventStream::create(...$matchingEventEnvelopes);
    }

    public function append(Events|Event $events, AppendCondition|null $condition = null): void
    {
        if ($condition !== null) {
            $lastEventEnvelope = $this->read($condition->failIfEventsMatch, ReadOptions::create(backwards: true))->first();
            if ($lastEventEnvelope !== null) {
                if ($condition->after === null) {
                    throw ConditionalAppendFailed::becauseMatchingEventsExist();
                }
                if ($condition->after->value < $lastEventEnvelope->position->value) {
                    throw ConditionalAppendFailed::becauseMatchingEventsExistAfterSequencePosition($condition->after);
                }
            }
        }
        $sequenceNumber = SequencePosition::fromInteger(count($this->eventEnvelopes) + 1);
        $newEventEnvelopes = SequencedEvents::none();
        if ($events instanceof Event) {
            $events = Events::fromArray([$events]);
        }
        foreach ($events as $event) {
            $newEventEnvelopes = $newEventEnvelopes->append(
                new SequencedEvent(
                    $sequenceNumber,
                    $this->clock->now(),
                    $event,
                ),
            );
            $sequenceNumber = $sequenceNumber->next();
        }
        $this->eventEnvelopes = $this->eventEnvelopes->append($newEventEnvelopes);
    }
}

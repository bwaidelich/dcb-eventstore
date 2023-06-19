<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helper;

use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Exception\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Model\EventEnvelope;
use Wwwision\DCBEventStore\Model\Events;
use Wwwision\DCBEventStore\Model\ExpectedLastEventId;
use Wwwision\DCBEventStore\Model\SequenceNumber;
use Wwwision\DCBEventStore\Model\StreamQuery;

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

    public function setup(): void
    {
        // In-memory event store does not need any setup
    }

    public function streamAll(): EventStream
    {
        return InMemoryEventStream::create(...$this->eventEnvelopes);
    }

    public function stream(StreamQuery $query): InMemoryEventStream
    {
        return InMemoryEventStream::create(...array_filter($this->eventEnvelopes, static fn (EventEnvelope $eventEnvelope) => $query->matches($eventEnvelope->event)));
    }

    public function conditionalAppend(Events $events, StreamQuery $query, ExpectedLastEventId $expectedLastEventId): void
    {
        $lastEvent = $this->stream($query)->last();
        if ($lastEvent === null) {
            if (!$expectedLastEventId->isNone()) {
                throw ConditionalAppendFailed::becauseNoEventMatchedTheQuery();
            }
        } elseif ($expectedLastEventId->isNone()) {
            throw ConditionalAppendFailed::becauseNoEventWhereExpected();
        } elseif (!$expectedLastEventId->matches($lastEvent->event->id)) {
            throw ConditionalAppendFailed::becauseEventIdsDontMatch($expectedLastEventId, $lastEvent->event->id);
        }
        $this->append($events);
    }

    public function append(Events $events): void
    {
        $sequenceNumber = SequenceNumber::fromInteger(count($this->eventEnvelopes));
        foreach ($events as $event) {
            $sequenceNumber = $sequenceNumber->next();
            $this->eventEnvelopes[] = new EventEnvelope(
                $sequenceNumber,
                $event,
            );
        }
    }
}

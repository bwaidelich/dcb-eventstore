<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helper;

use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Exception\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Model\EventEnvelope;
use Wwwision\DCBEventStore\Model\EventId;
use Wwwision\DCBEventStore\Model\Events;
use Wwwision\DCBEventStore\Model\SequenceNumber;
use Wwwision\DCBEventStore\Model\StreamQuery;

use function count;

/**
 * An in-memory implementation of the {@see EventStore} interface that mostly serves testing or debugging purposes
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

    public function stream(StreamQuery $query): InMemoryEventStream
    {
        return InMemoryEventStream::create(...array_filter($this->eventEnvelopes, static fn (EventEnvelope $eventEnvelope) => $query->matches($eventEnvelope->event)));
    }

    public function append(Events $events, StreamQuery $query, ?EventId $lastEventId): void
    {
        $lastEvent = $this->stream($query)->last();
        if ($lastEvent === null) {
            if ($lastEventId !== null) {
                throw ConditionalAppendFailed::becauseNoEventMatchedTheQuery();
            }
        } elseif ($lastEventId === null) {
            throw ConditionalAppendFailed::becauseNoEventWhereExpected();
        } elseif (!$lastEvent->event->id->equals($lastEventId)) {
            throw ConditionalAppendFailed::becauseEventIdsDontMatch($lastEventId, $lastEvent->event->id);
        }
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

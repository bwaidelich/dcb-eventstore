<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\InMemoryEventStore;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Wwwision\DCBEventStore\AppendCondition\AppendCondition;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\Events;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\ReadOptions;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvent;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvents;
use Wwwision\DCBEventStore\SequencedEvent\SequencePosition;

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
 * $inMemoryStream = $eventStore->read($query);
 */
final class InMemoryEventStore implements EventStore
{
    /**
     * @var array<SequencedEvent>
     */
    private array $sequencedEvents = [];

    private function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public static function create(ClockInterface|null $clock = null): self
    {
        return new self($clock ?? new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }
        });
    }

    public function read(Query $query, ReadOptions|null $options = null): SequencedEvents
    {
        $options ??= ReadOptions::create();

        if (!$query->hasItems()) {
            $sequencedEvents = $this->sequencedEvents;
        } else {
            /** @var array<int,SequencedEvent> $matchingSequencedEventsBySequencePosition */
            $matchingSequencedEventsBySequencePosition = [];
            foreach ($query as $queryItem) {
                if ($queryItem->onlyLastEvent) {
                    $sequencedEvents = array_reverse(iterator_to_array($this->sequencedEvents));
                } else {
                    $sequencedEvents = $this->sequencedEvents;
                }
                foreach ($sequencedEvents as $sequencedEvent) {
                    $sequencePosition = $sequencedEvent->position->value;
                    if (!$queryItem->matchesEvent($sequencedEvent->event)) {
                        continue;
                    }
                    $matchingSequencedEventsBySequencePosition[$sequencePosition] = $sequencedEvent;
                    if ($queryItem->onlyLastEvent) {
                        continue 2;
                    }
                }
            }
            ksort($matchingSequencedEventsBySequencePosition, SORT_NUMERIC);
            $sequencedEvents = array_values($matchingSequencedEventsBySequencePosition);
        }
        if ($options->backwards) {
            $sequencedEvents = array_reverse(iterator_to_array($sequencedEvents));
        }
        $matchingSequencedEvents = [];
        foreach ($sequencedEvents as $sequencedEvent) {
            $sequencePosition = $sequencedEvent->position->value;
            if ($options->from !== null && (($options->backwards && $sequencePosition > $options->from->value) || (!$options->backwards && $sequencePosition < $options->from->value))) {
                continue;
            }
            $matchingSequencedEvents[] = $sequencedEvent;
        }
        if ($options->limit !== null) {
            $matchingSequencedEvents = array_slice($matchingSequencedEvents, 0, $options->limit);
        }
        return SequencedEvents::fromArray($matchingSequencedEvents);
    }

    public function append(Events|Event $events, AppendCondition|null $condition = null): void
    {
        if ($condition !== null) {
            $lastSequencedEvent = $this->read($condition->failIfEventsMatch, ReadOptions::create(backwards: true))->first();
            if ($lastSequencedEvent !== null) {
                if ($condition->after === null) {
                    throw ConditionalAppendFailed::becauseMatchingEventsExist();
                }
                if ($condition->after->value < $lastSequencedEvent->position->value) {
                    throw ConditionalAppendFailed::becauseMatchingEventsExistAfterSequencePosition($condition->after);
                }
            }
        }
        $sequencePosition = SequencePosition::fromInteger(count($this->sequencedEvents) + 1);
        $newSequencedEvents = [];
        if ($events instanceof Event) {
            $events = Events::fromArray([$events]);
        }
        foreach ($events as $event) {
            $newSequencedEvents[] = new SequencedEvent(
                $sequencePosition,
                $this->clock->now(),
                $event,
            );
            $sequencePosition = $sequencePosition->next();
        }
        $this->sequencedEvents = [...$this->sequencedEvents, ...$newSequencedEvents];
    }
}

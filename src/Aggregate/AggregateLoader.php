<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Aggregate;

use Closure;
use Wwwision\DCBEventStore\EventNormalizer;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Exception\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Model\DomainEvents;
use Wwwision\DCBEventStore\Model\DomainIds;
use Wwwision\DCBEventStore\Model\Events;
use Wwwision\DCBEventStore\Model\EventTypes;
use Wwwision\DCBEventStore\Model\StreamQuery;

use function array_map;
use function iterator_to_array;

/**
 * Helper to interact with one or more event-sourced Aggregate(s) in a transaction-safe manner.
 *
 * @see AggregateLoader::transactional()
 *
 * Usage:
 *
 * $loader = new AggregateLoader($eventStore, $eventNormalizer);
 * $someAggregate = new SomeAggregate();
 * $loader->transactional(
 *   fn () => $someAggregate->doSomething(),
 *   $someAggregate
 * );
 */
final readonly class AggregateLoader
{
    public function __construct(
        private EventStore $eventStore,
        private EventNormalizer $eventNormalizer,
    ) {
    }

    /**
     * With this method...
     * 1. a {@see StreamQuery} is built using the {@see DomainIds} and {@see EventTypes} of all specified $aggregates
     * 2. all specified $aggregates are reconstituted from the {@see EventStore} using that query
     * 3. the specified $callback is invoked allowing to interact with the specified $aggregates
     * 4. all events produced during the callback are persisted to the Event Store via {@see EventStore::conditionalAppend()}
     *
     * @param Closure $callback
     * @param Aggregate ...$aggregates
     * @throws ConditionalAppendFailed
     */
    public function transactional(Closure $callback, Aggregate ...$aggregates): void
    {
        $domainIds = DomainIds::none();
        $eventTypes = EventTypes::none();
        foreach ($aggregates as $aggregate) {
            $domainIds = $domainIds->merge($aggregate->domainIds());
            $eventTypes = $eventTypes->merge($aggregate->eventTypes());
        }
        $query = new StreamQuery($domainIds, $eventTypes);
        $lastEventId = null;
        foreach ($this->eventStore->stream($query) as $eventEnvelope) {
            $domainEvent = $this->eventNormalizer->convertEvent($eventEnvelope);
            foreach ($aggregates as $aggregate) {
                if ($aggregate->domainIds()->intersects($domainEvent->domainIds())) {
                    $aggregate->apply($domainEvent);
                }
            }
            $lastEventId = $eventEnvelope->event->id;
        }
        $callback();
        $domainEvents = DomainEvents::none();
        foreach ($aggregates as $aggregate) {
            $domainEvents = $domainEvents->append($aggregate->pullRecordedEvents());
        }
        $convertedEvents = Events::fromArray(array_map($this->eventNormalizer->convertDomainEvent(...), iterator_to_array($domainEvents)));
        $this->eventStore->conditionalAppend($convertedEvents, $query, $lastEventId);
    }
}

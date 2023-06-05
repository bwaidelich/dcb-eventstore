<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Aggregate;

use Wwwision\DCBEventStore\EventNormalizer;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Model\DomainEvents;
use Wwwision\DCBEventStore\Model\DomainIds;
use Wwwision\DCBEventStore\Model\Events;
use Wwwision\DCBEventStore\Model\EventTypes;
use Wwwision\DCBEventStore\Model\StreamQuery;
use function array_map;

final readonly class AggregateLoader
{

    public function __construct(
        private EventStore $eventStore,
        private EventNormalizer $eventNormalizer,
    ) {
    }

    public function transactional(\Closure $callback, Aggregate ...$aggregates): void
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
        $convertedEvents = Events::fromArray(array_map($this->eventNormalizer->convertDomainEvent(...), $domainEvents));
        $this->eventStore->conditionalAppend($convertedEvents, $query, $lastEventId);
    }
}

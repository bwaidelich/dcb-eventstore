<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

/**
 * The raw low-level event that is stored in the Event Store
 */
final readonly class Event
{
    public function __construct(
        public EventId $id,
        public EventType $type,
        public EventData $data,
        public DomainIds $domainIds,
    ) {
    }
}

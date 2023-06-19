<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

/**
 * A Query describing events by their {@see DomainIds} and/or {@see EventTypes}
 */
final readonly class StreamQuery
{
    private function __construct(
        public ?DomainIds $domainIds,
        public ?EventTypes $types,
    ) {
    }

    /**
     * Creates an instance that only matches events with the specified {@see DomainIds} _and_ {@see EventTypes}
     */
    public static function matchingIdsAndTypes(DomainIds|DomainId $domainIds, EventTypes $eventTypes): self
    {
        if ($domainIds instanceof DomainId) {
            $domainIds = DomainIds::create($domainIds);
        }
        return new self($domainIds, $eventTypes);
    }

    /**
     * Creates an instance that matches all events with the specified {@see DomainIds}
     */
    public static function matchingIds(DomainIds|DomainId $domainIds): self
    {
        if ($domainIds instanceof DomainId) {
            $domainIds = DomainIds::create($domainIds);
        }
        return new self($domainIds, null);
    }

    /**
     * Creates an instance that matches all events with the specified {@see EventTypes}
     */
    public static function matchingTypes(EventTypes $eventTypes): self
    {
        return new self(null, $eventTypes);
    }

    public function matches(Event $event): bool
    {
        if ($this->domainIds !== null && !$this->domainIds->intersects($event->domainIds)) {
            return false;
        }
        if ($this->types !== null && !$this->types->contains($event->type)) {
            return false;
        }
        return true;
    }
}

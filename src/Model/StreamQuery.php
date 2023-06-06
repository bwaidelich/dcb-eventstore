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
    public static function matchingIdsAndTypes(DomainIds $domainIds, EventTypes $eventTypes): self
    {
        return new self($domainIds, $eventTypes);
    }

    /**
     * Creates an instance that matches all events with the specified {@see DomainIds}
     */
    public static function matchingIds(DomainIds $domainIds): self
    {
        return new self($domainIds, null);
    }

    /**
     * Creates an instance that matches all events with the specified {@see EventTypes}
     */
    public static function matchingTypes(EventTypes $eventTypes): self
    {
        return new self(null, $eventTypes);
    }

    /**
     * Creates an instance that does not match any event
     */
    public static function matchingNone(): self
    {
        return new self(DomainIds::none(), EventTypes::none());
    }

    /**
     * Creates an instance that matches all events
     */
    public static function matchingAny(): self
    {
        return new self(null, null);
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

    public function matchesNone(): bool
    {
        return $this->domainIds?->isNone() || $this->types?->isNone();
    }
}

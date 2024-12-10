<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery\Criteria;

use InvalidArgumentException;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventType;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\Tag;
use Wwwision\DCBEventStore\Types\Tags;

final class EventTypesAndTagsCriterion
{
    private function __construct(
        public readonly EventTypes|null $eventTypes,
        public readonly Tags|null $tags,
        public readonly bool $onlyLastEvent,
    ) {
    }

    /**
     * @param EventTypes|array<string|EventType>|string|null $eventTypes
     * @param Tags|array<string|Tag>|string|null $tags
     */
    public static function create(
        EventTypes|array|string|null $eventTypes = null,
        Tags|array|string|null $tags = null,
        bool|null $onlyLastEvent = null,
    ): self {
        if (is_string($eventTypes)) {
            $eventTypes = EventTypes::fromStrings($eventTypes);
        } elseif (is_array($eventTypes)) {
            $eventTypes = EventTypes::fromArray($eventTypes);
        }
        if (is_string($tags)) {
            $tags = Tags::single($tags);
        } elseif (is_array($tags)) {
            $tags = Tags::fromArray($tags);
        }
        if ($eventTypes === null && $tags === null) {
            throw new InvalidArgumentException('one of eventTypes or tags must not be null!', 1716131425);
        }
        return new self($eventTypes, $tags, $onlyLastEvent ?? false);
    }

    /**
     * @param EventTypes|array<string|EventType>|string|null $eventTypes
     * @param Tags|array<string|Tag>|string|null $tags
     */
    public function with(
        EventTypes|array|string|null $eventTypes = null,
        Tags|array|string|null $tags = null,
        bool|null $onlyLastEvent = null,
    ): self {
        if (is_string($eventTypes)) {
            $eventTypes = EventTypes::fromStrings($eventTypes);
        } elseif (is_array($eventTypes)) {
            $eventTypes = EventTypes::fromArray($eventTypes);
        }
        if (is_string($tags)) {
            $tags = Tags::single($tags);
        } elseif (is_array($tags)) {
            $tags = Tags::fromArray($tags);
        }
        return new self(
            $eventTypes ?? $this->eventTypes,
            $tags ?? $this->tags,
            $onlyLastEvent ?? $this->onlyLastEvent,
        );
    }

    public function matchesEvent(Event $event): bool
    {
        if ($this->tags !== null && !$event->tags->containEvery($this->tags)) {
            return false;
        }
        if ($this->eventTypes !== null && !$this->eventTypes->contain($event->type)) {
            return false;
        }
        return true;
    }
}

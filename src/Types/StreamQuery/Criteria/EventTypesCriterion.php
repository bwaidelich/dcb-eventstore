<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery\Criteria;

use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\StreamQuery\Criterion;

final readonly class EventTypesCriterion implements Criterion
{
    public function __construct(
        public EventTypes $eventTypes,
    ) {
    }

    public function matches(Event $event): bool
    {
        return $this->eventTypes->contain($event->type);
    }
}

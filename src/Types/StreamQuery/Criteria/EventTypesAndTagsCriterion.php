<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery\Criteria;

use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\StreamQuery\Criterion;
use Wwwision\DCBEventStore\Types\Tags;

final class EventTypesAndTagsCriterion implements Criterion
{
    public function __construct(
        public readonly EventTypes $eventTypes,
        public readonly Tags $tags,
    ) {
    }

    public function matches(Event $event): bool
    {
        return $this->eventTypes->contain($event->type) && $event->tags->containEvery($this->tags);
    }
}

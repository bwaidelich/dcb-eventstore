<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\Model\DomainIds;
use Wwwision\DCBEventStore\Model\Event;
use Wwwision\DCBEventStore\Model\EventTypes;

final readonly class StreamQuery
{
    public function __construct(
        public ?DomainIds $domainIds,
        public ?EventTypes $types,
    ) {
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

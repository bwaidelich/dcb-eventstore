<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\Model\DomainEvent;
use Wwwision\DCBEventStore\Model\DomainEvents;
use Wwwision\DCBEventStore\Model\DomainIds;
use Wwwision\DCBEventStore\Model\EventTypes;

interface Aggregate
{
    public function apply(DomainEvent $domainEvent): void;

    public function pullRecordedEvents(): DomainEvents;

    public function domainIds(): DomainIds;

    public function eventTypes(): EventTypes;
}
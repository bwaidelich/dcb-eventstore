<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\Model\DomainEvent;
use Wwwision\DCBEventStore\Model\Event;
use Wwwision\DCBEventStore\Model\EventEnvelope;

/**
 * Contract for an Event Normalizer that supports (de-)serialization of {@see DomainEvent} instances to raw {@see Event} instances vice versa
 */
interface EventNormalizer
{
    public function convertEvent(Event|EventEnvelope $event): DomainEvent;

    public function convertDomainEvent(DomainEvent $domainEvent): Event;
}

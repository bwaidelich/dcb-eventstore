<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helper;

use Wwwision\DCBEventStore\Model\DomainEvent;
use Wwwision\DCBEventStore\Model\DomainEvents;

trait AggregateTrait
{
    private ?DomainEvents $recordedEvents = null;

    abstract public function apply(DomainEvent $domainEvent): void;

    final protected function record(DomainEvent $domainEvent): void
    {
        $this->apply($domainEvent);
        if ($this->recordedEvents === null) {
            $this->recordedEvents = DomainEvents::none();
        }
        $this->recordedEvents = $this->recordedEvents->append($domainEvent);
    }

    final public function pullRecordedEvents(): DomainEvents
    {
        $domainEvents = $this->recordedEvents ?? DomainEvents::none();
        $this->recordedEvents = DomainEvents::none();
        return $domainEvents;
    }
}

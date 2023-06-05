<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Aggregate;

use Wwwision\DCBEventStore\Model\DomainEvent;
use Wwwision\DCBEventStore\Model\DomainEvents;

/**
 * Trait that _can_ be implemented by aggregate classes in order to satisfy the {@see Aggregate} interface
 */
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

<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Aggregate;

use Wwwision\DCBEventStore\Model\DomainEvent;
use Wwwision\DCBEventStore\Model\DomainEvents;
use Wwwision\DCBEventStore\Model\DomainIds;
use Wwwision\DCBEventStore\Model\EventTypes;

/**
 * Contract for an Event-Sourced Aggregate
 * The {@see AggregateLoader} can be used to interact with the aggregate in a transaction-safe manner
 *
 * Example implementation:
 *
 * final class CustomerNameAggregate implements Aggregate {
 *
 *   use AggregateTrait;
 *   private string $customerName = '';
 *
 *   public function __construct(private string $customerId) {}
 *
 *   public function apply(DomainEvent $domainEvent): void {
 *     $this->customerName = match ($domainEvent::class) {
 *       CustomerAdded::class => $domainEvent->customerName,
 *       CustomerRenamed::class => $domainEvent->newCustomerName,
 *       default => $this->customerName,
 *     };
 *   }
 *
 *   public function renameCustomer(string $newCustomerName): void {
 *     if ($newCustomerName === $this->customerName) {
 *       throw new ConstraintException('Customer name has not changed');
 *     }
 *     $this->record(new CustomerRenamed($this->customerId, $newCustomerName));
 *   }
 *
 *   public function domainIds(): DomainIds {
 *     return DomainIds::create($this->customerId);
 *   }
 *
 *   public function eventTypes(): EventTypes {
 *     return EventTypes::fromStrings('CustomerAdded', 'CustomerRenamed');
 *   }
 * }
 */
interface Aggregate
{
    public function apply(DomainEvent $domainEvent): void;

    public function domainIds(): DomainIds;

    public function eventTypes(): EventTypes;

    public function pullRecordedEvents(): DomainEvents;
}

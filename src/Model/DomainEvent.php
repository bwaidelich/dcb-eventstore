<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

/**
 * Contract for a Domain Event
 *
 * Example:
 *
 * final readonly class CustomerRenamed implements DomainEvent {
 *   public function __construct(
 *     public CustomerId $customerId,
 *     public CustomerName $newName,
 *   ) {}
 *
 *   public function domainIds(): DomainIds {
 *     return DomainIds::create($this->customerId);
 *   }
 * }
 */
interface DomainEvent
{
    /**
     * The Domain Ids that are affected by this Domain Ids
     */
    public function domainIds(): DomainIds;
}

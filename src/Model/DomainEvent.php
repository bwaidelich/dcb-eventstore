<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

interface DomainEvent {

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self;

    public function domainIds(): DomainIds;
}
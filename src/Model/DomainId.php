<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

/**
 * Contract for the Identifier of an Entity in the Domain
 */
interface DomainId
{
    public function key(): string;

    public function value(): string;
}

<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

interface DomainId
{
    public function key(): string;

    public function value(): string;
}
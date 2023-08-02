<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helpers;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Wwwision\DCBEventStore\EventStream;

final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}

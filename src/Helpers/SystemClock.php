<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helpers;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}

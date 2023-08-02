<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery;

use Wwwision\DCBEventStore\Types\Event;

/**
 * Common marker interface for {@see StreamQuery} criteria
 *
 * @internal This is not meant to be implemented by external packages!
 */
interface Criterion
{
    public function matches(Event $event): bool;
}

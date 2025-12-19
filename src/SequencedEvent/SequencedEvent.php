<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\SequencedEvent;

use DateTimeImmutable;
use Wwwision\DCBEventStore\Event\Event;

/**
 * An {@see Event} with its global {@see SequencePosition} in the Event Store
 */
final class SequencedEvent
{
    public function __construct(
        public readonly SequencePosition $position,
        public readonly DateTimeImmutable $recordedAt,
        public readonly Event $event,
    ) {}
}

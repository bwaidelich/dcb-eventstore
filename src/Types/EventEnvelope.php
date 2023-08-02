<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use DateTimeImmutable;

/**
 * An {@see Event} with its global {@see SequenceNumber} in the Events Store
 *
 *
 */
final readonly class EventEnvelope
{
    public function __construct(
        public SequenceNumber $sequenceNumber,
        //public DateTimeImmutable $recordedAt, // do we need it
        public Event $event,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use DateTimeImmutable;

/**
 * An {@see Event} with its global {@see SequenceNumber} in the Events Store
 */
final class EventEnvelope
{
    public function __construct(
        public readonly SequenceNumber $sequenceNumber,
        public DateTimeImmutable $recordedAt,
        public readonly Event $event,
    ) {
    }
}

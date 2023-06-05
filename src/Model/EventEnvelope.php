<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

/**
 * An {@see Event} with its global {@see SequenceNumber} in the Event Store
 *
 *
 */
final readonly class EventEnvelope
{
    public function __construct(
        public SequenceNumber $sequenceNumber,
        public Event $event,
    ) {
    }
}

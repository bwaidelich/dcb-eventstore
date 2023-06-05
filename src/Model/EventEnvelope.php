<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Model;

final readonly class EventEnvelope
{
    public function __construct(
        public SequenceNumber $sequenceNumber,
        public Event $event,
    ) {}
}
<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

/**
 * The raw low-level event that is stored in the Events Store
 */
final class Event
{
    public function __construct(
        public readonly EventId $id, // required for deduplication – TODO really? the sequenceNumber should work
        public readonly EventType $type,
        public readonly EventData $data, // opaque, no size limit?
        public readonly Tags $tags,
        public readonly EventMetadata $metadata,
    ) {
    }
}

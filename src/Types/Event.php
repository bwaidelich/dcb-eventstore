<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

/**
 * The raw low-level event that is stored in the Events Store
 */
final readonly class Event
{
    public function __construct(
        public EventId $id, // required for deduplication
        public EventType $type,
        public EventData $data, // opaque, no size limit?
        public Tags $tags,
        // add metadata ?
    ) {
    }
}

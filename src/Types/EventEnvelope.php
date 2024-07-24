<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use DateTimeImmutable;
use Wwwision\DCBEventStore\Types\StreamQuery\CriterionHashes;

/**
 * An {@see Event} with its global {@see SequenceNumber} in the Events Store
 */
final class EventEnvelope
{
    public function __construct(
        public readonly SequenceNumber $sequenceNumber,
        public readonly DateTimeImmutable $recordedAt,
        public readonly CriterionHashes $criterionHashes,
        public readonly Event $event,
    ) {
    }

    public function withCriterionHashes(CriterionHashes $criterionHashes): self
    {
        return new self($this->sequenceNumber, $this->recordedAt, $criterionHashes, $this->event);
    }
}

<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Helper;

use Traversable;
use Webmozart\Assert\Assert;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Model\EventEnvelope;
use Wwwision\DCBEventStore\Model\SequenceNumber;

final class BatchEventStream implements EventStream
{

    private function __construct(
        private EventStream $wrappedEventStream,
        private readonly int $batchSize,
        private readonly ?SequenceNumber $minimumSequenceNumber,
        private readonly ?int $limit,
    ) {}

    public static function create(EventStream $wrappedEventStream, int $batchSize): self
    {
        Assert::notInstanceOf($wrappedEventStream, self::class, 'Cannot wrap BatchEventStream in itself');
        return new self($wrappedEventStream, $batchSize, null, null);
    }

    public function withMinimumSequenceNumber(SequenceNumber $sequenceNumber): EventStream
    {
        if ($this->minimumSequenceNumber !== null && $sequenceNumber->equals($this->minimumSequenceNumber)) {
            return $this;
        }
        return new self($this->wrappedEventStream, $this->batchSize, $sequenceNumber, $this->limit);
    }

    public function limit(int $limit): self
    {
        if ($limit === $this->limit) {
            return $this;
        }
        return new self($this->wrappedEventStream, $this->batchSize, $this->minimumSequenceNumber, $limit);
    }

    public function last(): ?EventEnvelope
    {
        return $this->wrappedEventStream->last();
    }

    public function getIterator(): Traversable
    {
        $this->wrappedEventStream = $this->wrappedEventStream->limit($this->batchSize);
        if ($this->minimumSequenceNumber !== null) {
            $this->wrappedEventStream = $this->wrappedEventStream->withMinimumSequenceNumber($this->minimumSequenceNumber);
        }
        $iteration = 0;
        do {
            $eventEnvelope = null;
            foreach ($this->wrappedEventStream as $eventEnvelope) {
                yield $eventEnvelope;
                $iteration ++;
                if ($this->limit !== null && $iteration >= $this->limit) {
                    return;
                }
            }
            if ($eventEnvelope === null) {
                return;
            }
            $this->wrappedEventStream = $this->wrappedEventStream->withMinimumSequenceNumber($eventEnvelope->sequenceNumber->next());
        } while (true);
    }
}
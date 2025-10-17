<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types;

use Wwwision\DCBEventStore\EventStore;

/**
 * Condition for {@see EventStore::read()} and {@see EventStore::readAll()}
 */
final class ReadOptions
{
    /**
     * @param SequenceNumber|null $from If specified, only events with the given {@see SequenceNumber} or a higher (lower if backwards) one will be returned
     * @param bool $backwards If true, events will be returned in descending order, otherwise in the order they were appended
     */
    private function __construct(
        public readonly SequenceNumber|null $from,
        public bool $backwards,
    ) {}

    public static function create(
        SequenceNumber|int|null $from = null,
        bool|null $backwards = null,
    ): self {
        if (is_int($from)) {
            $from = SequenceNumber::fromInteger($from);
        }
        return new self(
            $from,
            $backwards ?? false,
        );
    }

    public function with(
        SequenceNumber|int|null $from = null,
        bool|null $backwards = null,
    ): self {
        if (is_int($from)) {
            $from = SequenceNumber::fromInteger($from);
        }
        return new self(
            $from ?? $this->from,
            $backwards ?? $this->backwards,
        );
    }
}

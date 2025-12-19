<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\SequencedEvent\SequencePosition;

/**
 * Condition for {@see EventStore::read()} and {@see EventStore::readAll()}
 */
final class ReadOptions
{
    private function __construct(
        public readonly SequencePosition|null $from,
        public readonly int|null $limit,
        public bool $backwards,
    ) {}

    /**
     * NOTE: this method is meant to be used with named arguments, since additional parameters might be added in the future: `ReadOptions::create(backwards: true)`
     *
     * @param SequencePosition|int|null $from If specified, only events with the given {@see SequencePosition} or a higher (lower if backwards) one will be returned
     * @param int|null $limit If specified, only the specified number of events will be read
     * @param bool|null $backwards If true, events will be returned in descending order, otherwise in the order they were appended
     */
    public static function create(
        SequencePosition|int|null $from = null,
        int|null $limit = null,
        bool|null $backwards = null,
    ): self {
        if (is_int($from)) {
            $from = SequencePosition::fromInteger($from);
        }
        return new self(
            $from,
            $limit,
            $backwards ?? false,
        );
    }
}

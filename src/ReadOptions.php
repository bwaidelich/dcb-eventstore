<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\Event\SequencePosition;

/**
 * Condition for {@see EventStore::read()} and {@see EventStore::readAll()}
 */
final class ReadOptions
{
    /**
     * @param SequencePosition|null $from If specified, only events with the given {@see SequencePosition} or a higher (lower if backwards) one will be returned
     * @param bool $backwards If true, events will be returned in descending order, otherwise in the order they were appended
     */
    private function __construct(
        public readonly SequencePosition|null $from,
        public bool $backwards,
    ) {}

    /**
     * NOTE: this method is meant to be used with named arguments, since additional parameters might be added in the future: `ReadOptions::create(backwards: true)`
     */
    public static function create(
        SequencePosition|int|null $from = null,
        bool|null $backwards = null,
    ): self {
        if (is_int($from)) {
            $from = SequencePosition::fromInteger($from);
        }
        return new self(
            $from,
            $backwards ?? false,
        );
    }
}

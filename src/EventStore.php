<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore;

use Wwwision\DCBEventStore\Exception\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Model\EventId;
use Wwwision\DCBEventStore\Model\Events;

interface EventStore
{
    public function setup(): void;

    public function stream(StreamQuery $query): EventStream;

    public function append(Events $events): void;

    /**
     * @throws ConditionalAppendFailed
     */
    public function conditionalAppend(Events $events, StreamQuery $query, ?EventId $lastEventId): void;
}
<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Helpers\InMemoryEventStore;
use Wwwision\DCBEventStore\Helpers\InMemoryEventStream;
use Wwwision\DCBEventStore\Tests\Integration\EventStoreTestBase;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventData;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\EventType;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\ExpectedHighestSequenceNumber;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBEventStore\Types\Tags;

#[CoversClass(InMemoryEventStore::class)]
#[CoversClass(InMemoryEventStream::class)]
#[CoversClass(Tags::class)]
#[CoversClass(EventData::class)]
#[CoversClass(ConditionalAppendFailed::class)]
#[CoversClass(EventEnvelope::class)]
#[CoversClass(ExpectedHighestSequenceNumber::class)]
#[CoversClass(EventType::class)]
#[CoversClass(EventTypes::class)]
#[CoversClass(Event::class)]
#[CoversClass(Events::class)]
#[CoversClass(SequenceNumber::class)]
#[CoversClass(StreamQuery::class)]
final class InMemoryEventStoreTest extends EventStoreTestBase
{
    protected function createEventStore(): InMemoryEventStore
    {
        return InMemoryEventStore::create();
    }

}

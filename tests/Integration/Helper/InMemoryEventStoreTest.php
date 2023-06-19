<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration\Helper;

use Wwwision\DCBEventStore\Exception\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Model\DomainIds;
use Wwwision\DCBEventStore\Model\Event;
use Wwwision\DCBEventStore\Model\EventData;
use Wwwision\DCBEventStore\Model\EventEnvelope;
use Wwwision\DCBEventStore\Model\EventId;
use Wwwision\DCBEventStore\Model\Events;
use Wwwision\DCBEventStore\Model\EventType;
use Wwwision\DCBEventStore\Model\EventTypes;
use Wwwision\DCBEventStore\Model\ExpectedLastEventId;
use Wwwision\DCBEventStore\Model\SequenceNumber;
use Wwwision\DCBEventStore\Model\StreamQuery;
use Wwwision\DCBEventStore\Tests\Integration\EventStoreTestBase;
use Wwwision\DCBEventStore\Helper\InMemoryEventStore;
use Wwwision\DCBEventStore\Helper\InMemoryEventStream;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryEventStore::class)]
#[CoversClass(InMemoryEventStream::class)]
#[CoversClass(DomainIds::class)]
#[CoversClass(EventData::class)]
#[CoversClass(ConditionalAppendFailed::class)]
#[CoversClass(EventEnvelope::class)]
#[CoversClass(ExpectedLastEventId::class)]
#[CoversClass(EventId::class)]
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
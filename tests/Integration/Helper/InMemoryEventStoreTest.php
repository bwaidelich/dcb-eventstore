<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Integration\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\EventData;
use Wwwision\DCBEventStore\Event\Events;
use Wwwision\DCBEventStore\Event\EventType;
use Wwwision\DCBEventStore\Event\EventTypes;
use Wwwision\DCBEventStore\Event\SequencePosition;
use Wwwision\DCBEventStore\Event\Tags;
use Wwwision\DCBEventStore\Exceptions\ConditionalAppendFailed;
use Wwwision\DCBEventStore\Helpers\InMemoryEventStore;
use Wwwision\DCBEventStore\Helpers\InMemoryEventStream;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\SequencedEvent\SequencedEvent;
use Wwwision\DCBEventStore\Tests\Integration\EventStoreTestBase;

#[CoversClass(InMemoryEventStore::class)]
#[CoversClass(InMemoryEventStream::class)]
#[CoversClass(Tags::class)]
#[CoversClass(EventData::class)]
#[CoversClass(ConditionalAppendFailed::class)]
#[CoversClass(SequencedEvent::class)]
#[CoversClass(EventType::class)]
#[CoversClass(EventTypes::class)]
#[CoversClass(Event::class)]
#[CoversClass(Events::class)]
#[CoversClass(SequencePosition::class)]
#[CoversClass(Query::class)]
final class InMemoryEventStoreTest extends EventStoreTestBase
{
    protected function createEventStore(): InMemoryEventStore
    {
        return InMemoryEventStore::create();
    }

}

# Dynamic Consistency Boundary Event Store

Interfaces and types for Event Stores implementing [Dynamic Consistency Boundaries](https://dcb.events/) according to the [specification](https://dcb.events/specification/).

To actually commit events, a corresponding [adapter package](#adapters) is required!

## Adapters

The following adapter implementations can be used with this package:

| Adapter                                                                                       | Storage/Engine                                                                      | Transport, SDK                                                                 |
|-----------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|--------------------------------------------------------------------------------|
| [ekvedaras/dcb-eventstore-illuminate](https://github.com/ekvedaras/dcb-eventstore-illuminate) | SQLite, MySQL/MariaDB, PostgreSQL                                                   | [Laravel Database](https://laravel.com/docs/12.x/database)                     |
| [wwwision/dcb-eventstore-doctrine](https://github.com/bwaidelich/dcb-eventstore-doctrine)     | SQLite, MySQL/MariaDB, PostgreSQL                                                   | [Doctrine DBAL](https://www.doctrine-project.org/projects/dbal.html)           |
| [wwwision/dcb-eventstore-esdb](https://github.com/bwaidelich/dcb-eventstore-esdb)             | [EventSourcing Database](https://www.eventsourcingdb.io/) (file based, proprietary) | HTTP                                                                           |
| [wwwision/dcb-eventstore-umadb](https://github.com/bwaidelich/dcb-eventstore-umadb)           | [UmaDB](https://umadb.io/) (file based, open source)                                | Rust FFI (via custom [PHP Extension](https://github.com/bwaidelich/umadb-php)) |
| [wwwision/dcb-eventstore-umadb-grpc](https://github.com/bwaidelich/dcb-eventstore-umadb-grpc) | [UmaDB](https://umadb.io/) (file based, open source)                                | gRPC (via official [PHP Extension](https://github.com/grpc/grpc))              |

_Feel free to contact me or extend this list via [pull request](https://github.com/bwaidelich/dcb-eventstore/pulls) if you wrote another adapter implementation_

## Usage

Install via [composer](https://getcomposer.org):

```shell
composer require wwwision/dcb-eventstore
```

### Create Event Store instance

Instantiation of Event Stores depend on the corresponding [adapter package](#adapters).
This package comes with an in-memory Event Store for testing, that can be created like so: 

```php
$eventStore = \Wwwision\DCBEventStore\InMemoryEventStore\InMemoryEventStore::create();
```

### Read Events

The `read()` function allows to read events.
To obtain a stream of all events in the Event Store, `Query::all()` can be used:

```php
use Wwwision\DCBEventStore\Query\Query;

$eventStream = $eventStore->read(Query::all());
```

The result is an iterable stream of `SequencedEvents`, that contain the originally appended event, the `position` of that event in the stream and some metadata:

```php
// ...
foreach ($eventStream as $sequencedEvent) {
  $tags = implode(', ', $sequencedEvent->event->tags->toStrings());
  $metadata = print_r($sequencedEvent->event->metadata->value, true);
  echo "Position: {$sequencedEvent->position->value}\n";
  echo "Event type: {$sequencedEvent->event->type}\n";
  echo "Event tags: $tags\n";
  echo "Recorded at: {$sequencedEvent->recordedAt->format(DATE_ATOM)}\n";
  echo "Event data: {$sequencedEvent->event->data}\n";
  echo "Event metadata: $metadata\n";
  echo "----\n";
}
```

#### Filter events

`Query::fromItems()` can be used to filter events, by their type: 

```php
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;

// return only events of the type "SomeEventType"
$eventStore->read(
  Query::fromItems(
    QueryItem::create(eventTypes: 'SomeEventType')
  )
);
```

...by tags:

```php
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;

// return only events that are tagged "some:tag"
$eventStore->read(
  Query::fromItems(
    QueryItem::create(tags: 'some:tag')
  )
);
```

...or by a combination:

```php
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;

// return only events that are tagged with "some:tag" AND "some:other-tag" and are of type "SomeType" OR "SomeOtherType"
$eventStore->read(
  Query::fromItems(
    QueryItem::create(eventTypes: ['SomeType', 'SomeOtherType'], tags: ['some:tag', 'some:other-tag'])
  )
);
```

Multiple `QueryItem`s can be specified to filter events that match _any_ of the specified items:

```php
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;

// return only events that are tagged "some:tag" and are of type "SomeType" OR that are tagged "some:other-tag" and are of type "SomeOtherType"
$eventStore->read(
  Query::fromItems(
    QueryItem::create(eventTypes: 'SomeType', tags: 'some:tag'),
    QueryItem::create(eventTypes: 'SomeOtherType', tags: 'some:other-tag')
  )
);
```

> [!NOTE]
> Tags within a single QueryItem are conjunctive (combined with AND) while individual QueryItems are disjunctive (combined with OR)

### Read Options

An optional 2nd argument can be specified in order to define custom limits/orderings:

```php
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\ReadOptions;

// read 100 events starting from sequence position 1234:
$eventStore->read(
  Query::all(),
  ReadOptions::create(
    from: 1234,
    limit: 100,
  )
);
```

By default events are always ordered by their `SequencePosition` in _ascending_ order i.e. FIFO.
Sometimes it can be useful to order events in _descending_ order, for example in order to provide cursor-based pagination:

```php
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\ReadOptions;

// read 50 events before (and including) event at sequence number 321
$eventStore->read(
  Query::all(),
  ReadOptions::create(
    from: 321,
    limit: 50,
    backwards: true,
  )
);
```

This can also be used to load the last event(s) with a certain type or tag:

```php
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;
use Wwwision\DCBEventStore\ReadOptions;

// get last "InvoiceCreated" event (or NULL if none exists yet)
$lastInvoiceCreatedEvent = $eventStore->read(
  Query::fromItems(
    QueryItem::create(eventTypes: 'InvoiceCreated')
  ),
  ReadOptions::create(
    limit: 1,
    backwards: true,
  )
)->first();

$lastInvoiceNumber = $lastInvoiceCreatedEvent?->event->data->jsonDecode()['invoiceNumber'] ?? 0;
```

## Write Events

The `append()` function allows to write events.

### Unconditional writes

DCB is all about enforcing consistency when appending new events. But in some cases (e.g. when importing data or for testing purposes) it can be necessary to write events without enforcing any constraint.
Therefor, the `appendCondition` parameter can be left out: 

```php
use Wwwision\DCBEventStore\Event\Event;

// append a single event without conditions
$eventStore->append(
  Event::create(
    type: 'SomeEventType',
    data: ['foo' => 'bar', 'bar' => 'baz'],
    tags: ['tag1', 'tag2'],
  )
);
```

Multiple events can be written atomically using `Events`:

```php
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\Events;

// append two events atomically without conditions
$eventStore->append(
  Events::fromArray([
    Event::create(type: 'SomeEventType', data: 'data1'),
    Event::create(type: 'SomeOtherEventType', data: 'data2'),
  ])
);
```

### Append Condition

The following call appends a `ProductDefined` event, but fails if a corresponding event for the same product id was appended previously (or practically at the same time, i.e. this operation ensures transaction safety):

```php
use Wwwision\DCBEventStore\AppendCondition\AppendCondition;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\Events;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;

// append a single "ProductDefined" event only if no corresponding event with the same tag was appended previously
$eventStore->append(
  Event::create(type: 'ProductDefined', data: ['id' => 'p123', 'title' => 'Some product'], tags: ['product:p123']),
  condition: AppendCondition::create(
    failIfEventsMatch: Query::fromItems(QueryItem::create(eventTypes: 'ProductDefined', tags: 'product:p123')),
  ),
);
```

In the previous example, no event in the entire stream must match the specified query – it can be compared with a `NoStream` expectation of a traditional event store.
But DCB also supports to specify a "safe point" using the optional `after` parameter of the `AppendCondition`:

```php
use Wwwision\DCBEventStore\AppendCondition\AppendCondition;
use Wwwision\DCBEventStore\Event\Event;
use Wwwision\DCBEventStore\Event\Events;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;

// append a single "ProductPriceChanged" event only if no corresponding event with the same tag was appended after the safe point (sequence position 1234)
$eventStore->append(
  Event::create(type: 'ProductPriceChanged', data: ['id' => 'p123', 'newPrice' => 54321], tags: ['product:p123']),
  condition: AppendCondition::create(
    failIfEventsMatch: Query::fromItems(QueryItem::create(eventTypes: 'ProductPriceChanged', tags: 'product:p123')),
    after: 1234,
  ),
);
```

## Higher Level API

This package mainly implements the low-level DCB specification (see [dcb.events website](https://dcb.events/specification/)).
It's highly advised to introduce a higher level abstraction for the usage within the actual application logic.

Feel free to get in touch to see how this can be combined with the idea of [composed projections](https://dcb.events/topics/projections/#composing-projections) in practice!

## Contribution

Contributions in the form of [issues](https://github.com/bwaidelich/dcb-eventstore/issues), [pull requests](https://github.com/bwaidelich/dcb-eventstore/pulls) or [discussions](https://github.com/bwaidelich/dcb-eventstore/discussions) are highly appreciated

## License

See [LICENSE](./LICENSE)

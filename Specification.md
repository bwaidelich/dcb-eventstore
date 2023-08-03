## EventStore

### Reading

Expects a [StreamQuery](#StreamQuery) and an optional starting [SequenceNumber](#SequenceNumber) and returns an [EventStream](#EventStream).

**Note:** The EventStore should also allow for _backwards_ iteration on the EventStream in order to support cursor based pagination.

### Writing

Expects a set of [Events](#Events) and an [AppendCondition](#AppendCondition) and returns the last appended [SequenceNumber](#SequenceNumber).

#### Potential API

```
EventStore {

  read(query: StreamQuery, from?: SequenceNumber): EventStream
  
  readBackwards(query: StreamQuery, from?: SequenceNumber): EventStream
  
  append(events: Events, condition: AppendCondition): SequenceNumber
  
}
```

## StreamQuery

The `StreamQuery` describes constraints that must be matched by [Event](#Event)s in the [EventStore](#EventStore).

* It _MAY_ contain a set of [StreamQuery Criteria](#StreamQuery-Criterion)

**Note:** All criteria of a StreamQuery are merged into a *logical disjunction*, so the example below matches all events, that match the first **OR** the second criterion.

#### Potential serialization format

```json 
{
    "version": "1.0",
    "criteria": [{
        "type": "EventTypes",
        "properties": {
            "event_types": ["EventType1", "EventType2"]
        }
    }, {
        "type": "Tags",
        "properties": {
            "tags": ["foo:bar", "baz:foos"],
        }
    }, {
        "type": "EventTypesAndTags",
        "properties": {
            "event_types": ["EventType2", "EventType3"],
            "tags": ["foo:bar", "foo:baz"],
        }
    }]
}
```


## StreamQuery Criterion

In v1 the only supported criteria types are:

* `Tags` – allows to target one or more [Tags](#Tags)
* `EventTypes` – allows to target one or more [EventType](#EventType)s
* `EventTypesAndTags` – allows to target one or more [Tags](#Tags) and one or more [EventType](#EventType)s

## SequenceNumber

When an [Event](#Event) is appended to the [EventStore](#EventStore) a `SequenceNumber` is assigned to it.

It...
* _MUST_ be unique for one EventStore
* _MUST_ be monotonic increasing
* _MUST_ have an allowed minimum value of `1`
* _CAN_ contain gaps
* _SHOULD_ have a reasonably high maximum value (depending on programming language and environment)


## EventStream

When reading from the [EventStore](#EventStore) an `EventStream` is returned.

It...
* It _MUST_ be iterable
* It _MUST_ return an [EventEnvelope](#EventEnvelope) for every iteration
* It _CAN_ include new events if they occur during iteration
* Individual [EventEnvelope](#EventEnvelope) instances _MAY_ be converted during iteration for performance optimization
* Batches of events _MAY_ be loaded from the underlying storage at once for performance optimization

## EventEnvelope

Each item in the [EventStream](#EventStream) is an `EventEnvelope` that consists of the underlying event and metadata, like the [SequenceNumber](#SequenceNumber) that was added during the `append()` call.

```json
{
    "event": {
        "id": "15aaa216-4179-46d9-999a-75516e21a1c6",
        "type": "SomeEventType",
        "data": "{\"some\":\"data\"}"
        "tags": ["type1:value1", "type2:value2"]
    },
    "sequence_number": 1234
}
```

## Events

A set of [Event](#Event) instances that is passed to the `append()` method of the [EventStore](#EventStore)

It...
* _MUST_ not be empty
* _MUST_ be iterable, each iteration returning an [Event](#Event)

## Event

* It _MUST_ contain a globally unique [EventId](#EventId)
* It _MUST_ contain an [EventType](#EventType)
* It _MUST_ contain [EventData](#EventData)
* It _MAY_ contain [Tags](#Tags)

#### Potential serialization format

```json
{
    "id": "15aaa216-4179-46d9-999a-75516e21a1c6",
    "type": "SomeEventType",
    "data": "{\"some\":\"data\"}"
    "tags": ["key1:value1", "key1:value2"]
}
```

## EventId

String based globally unique identifier of an [Event](#Event)

* It _MUST_ satisfy the regular expression `^[\w\-]{1,100}$`
* It _MAY_ be implemented as a [UUID](https://www.ietf.org/rfc/rfc4122.txt)

## EventType

String based type of an event

* It _MUST_ satisfy the regular expression `^[\w\.\:\-]{1,200}$`

## EventData

String based, opaque payload of an [Event](#Event)

* It _SHOULD_ have a reasonable large enough maximum length (depending on language and environment)
* It _MAY_ contain [JSON](https://www.json.org/)
* It _MAY_ be serialized into an empty string

## Tags

A set of [Tag](#Tag) instances.

* It _MUST_ contain at least one [Tag](#Tag)
* It _MAY_ contain multiple [Tag](#Tag)s with the same value
* It _SHOULD_ not contain muliple [Tag](#Tag)s with the same key/value pair

## Tag

A `Tag` can add domain specific metadata (usually the ids of an entity or concept of the core domain) to an event allowing for custom partitioning

**NOTE:** If the `value` is not specified, all tags of the given `key` will match (wildcard)

* It _MUST_ contain a `key` that satisfies the regular expression `^[a-zA-Z0-9\-\_]{1,50}$`
* It _CAN_ contain a `value` that satisfies the regular expression `^[a-zA-Z0-9\-\_]{1,50}$`

## AppendCondition

* It _MUST_ contain a [StreamQuery](#StreamQuery)
* It _MUST_ contain a [ExpectedHighestSequenceNumber](#ExpectedHighestSequenceNumber)

## ExpectedHighestSequenceNumber

Can _either_ be an instance of [SequenceNumber](#SequenceNumber)
Or one of:
* `NONE` – No event must match the specified [StreamQuery](#StreamQuery)
* `ANY` – Any event matches (= wildcard [AppendCondition](#AppendCondition))

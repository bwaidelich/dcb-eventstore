<?php
declare(strict_types=1);

namespace Unit\Types\StreamQuery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Types\EventType;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\TagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuerySerializer;
use Wwwision\DCBEventStore\Types\Tags;

#[CoversClass(StreamQuerySerializer::class)]
final class StreamQuerySerializerTest extends TestCase
{
    public static function dataprovider_serialize(): iterable
    {
        yield ['query' => StreamQuery::create(Criteria::create(new TagsCriterion(Tags::single('foo', 'bar')))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"Tags","hash":"addb4f7ae3afe9ea5c8975ba330bf419","properties":{"tags":[{"key":"foo","value":"bar"}]}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(new EventTypesCriterion(EventTypes::single('SomeEventType')))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypes","hash":"ed73d849d917379fade8a8d4affeb1bd","properties":{"eventTypes":["SomeEventType"]}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(new EventTypesCriterion(EventTypes::create(EventType::fromString('SomeOtherEventType'), EventType::fromString('SomeEventType'))))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypes","hash":"a926cae309aa0cfc14ebbae0435c136f","properties":{"eventTypes":["SomeEventType","SomeOtherEventType"]}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(new EventTypesAndTagsCriterion(EventTypes::single('SomeEventType'), Tags::single('foo', 'bar')))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","hash":"1d8d7779dae8565a4378ed69ff9677a4","properties":{"eventTypes":["SomeEventType"],"tags":[{"key":"foo","value":"bar"}]}}]}'];
    }

    /**
     * @dataProvider dataprovider_serialize
     */
    public function test_serialize(StreamQuery $query, string $expectedResult): void
    {
        self::assertJsonStringEqualsJsonString($expectedResult, StreamQuerySerializer::serialize($query));
    }
}
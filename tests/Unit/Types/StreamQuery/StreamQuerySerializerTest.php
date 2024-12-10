<?php
declare(strict_types=1);

namespace Unit\Types\StreamQuery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuerySerializer;

#[CoversClass(StreamQuerySerializer::class)]
final class StreamQuerySerializerTest extends TestCase
{
    public static function dataprovider_serialize(): iterable
    {
        yield ['query' => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(tags: ['foo:bar']))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","properties":{"tags":["foo:bar"],"onlyLastEvent":false}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType']))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","properties":{"eventTypes":["SomeEventType"],"onlyLastEvent":false}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: ['SomeOtherEventType', 'SomeEventType']))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","properties":{"eventTypes":["SomeEventType","SomeOtherEventType"],"onlyLastEvent":false}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType', 'SomeOtherEventType'], tags: ['foo:bar'], onlyLastEvent: false))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","properties":{"eventTypes":["SomeEventType","SomeOtherEventType"],"tags":["foo:bar"],"onlyLastEvent":false}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType'], tags: ['foo:bar', 'baz:foos'], onlyLastEvent: true))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","properties":{"eventTypes":["SomeEventType"],"tags":["baz:foos","foo:bar"],"onlyLastEvent":true}}]}'];
    }

    /**
     * @dataProvider dataprovider_serialize
     */
    public function test_serialize(StreamQuery $query, string $expectedResult): void
    {
        self::assertJsonStringEqualsJsonString($expectedResult, StreamQuerySerializer::serialize($query));
    }
}
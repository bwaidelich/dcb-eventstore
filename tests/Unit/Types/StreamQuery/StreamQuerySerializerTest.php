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
        yield ['query' => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(tags: ['foo:bar']))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","hash":"a2d4473e4dd478bf4bc84b6dc39eeed4","properties":{"tags":[{"key":"foo","value":"bar"}],"onlyLastEvent":false}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType']))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","hash":"4e7de6454d2d1802ab1a89addb4e8faf","properties":{"eventTypes":["SomeEventType"],"onlyLastEvent":false}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: ['SomeOtherEventType', 'SomeEventType']))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","hash":"e4f666fc292f9ead35ac959d3a43fcf2","properties":{"eventTypes":["SomeEventType","SomeOtherEventType"],"onlyLastEvent":false}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType', 'SomeOtherEventType'], tags: ['foo:bar'], onlyLastEvent: false))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","hash":"c390815d2e7cffcd1a675817f89fb33c","properties":{"eventTypes":["SomeEventType","SomeOtherEventType"],"tags":[{"key":"foo","value":"bar"}],"onlyLastEvent":false}}]}'];
        yield ['query' => StreamQuery::create(Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: ['SomeEventType'], tags: ['foo:bar', 'baz:foos'], onlyLastEvent: true))), 'expectedResult' => '{"version":"1.0","criteria":[{"type":"EventTypesAndTags","hash":"af11a6a8247319ce67ca292b17d0bd06","properties":{"eventTypes":["SomeEventType"],"tags":[{"key":"baz","value":"foos"},{"key":"foo","value":"bar"}],"onlyLastEvent":true}}]}'];
    }

    /**
     * @dataProvider dataprovider_serialize
     */
    public function test_serialize(StreamQuery $query, string $expectedResult): void
    {
        self::assertJsonStringEqualsJsonString($expectedResult, StreamQuerySerializer::serialize($query));
    }
}
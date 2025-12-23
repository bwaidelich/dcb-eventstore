<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\AppendCondition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\AppendCondition\AppendCondition;
use Wwwision\DCBEventStore\Query\Query;
use Wwwision\DCBEventStore\Query\QueryItem;
use Wwwision\DCBEventStore\SequencedEvent\SequencePosition;

#[CoversClass(AppendCondition::class)]
#[Medium]
final class AppendConditionTest extends TestCase
{
    public function test_create_sets_properties(): void
    {
        $query = Query::fromItems(QueryItem::create(eventTypes: 'SomeType'));
        $position = SequencePosition::fromInteger(10);

        $condition = AppendCondition::create($query, $position);

        self::assertSame($query, $condition->failIfEventsMatch);
        self::assertSame($position, $condition->after);
    }

    public function test_create_without_after(): void
    {
        $query = Query::fromItems(QueryItem::create(tags: 'some-tag'));

        $condition = AppendCondition::create($query);

        self::assertSame($query, $condition->failIfEventsMatch);
        self::assertNull($condition->after);
    }

    public function test_properties_are_readonly(): void
    {
        $query = Query::fromItems(QueryItem::create(eventTypes: 'TestType'));
        $position = SequencePosition::fromInteger(5);

        $condition = AppendCondition::create($query, $position);

        self::assertSame($query, $condition->failIfEventsMatch);
        self::assertSame($position, $condition->after);
    }
}

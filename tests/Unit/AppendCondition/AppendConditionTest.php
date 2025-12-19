<?php

declare(strict_types=1);

namespace Unit\AppendCondition;

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
    public function test_constructor_sets_properties(): void
    {
        $query = Query::fromItems(QueryItem::create(eventTypes: 'SomeType'));
        $position = SequencePosition::fromInteger(10);

        $condition = new AppendCondition($query, $position);

        self::assertSame($query, $condition->failIfEventsMatch);
        self::assertSame($position, $condition->after);
    }

    public function test_constructor_with_null_after(): void
    {
        $query = Query::fromItems(QueryItem::create(tags: 'some-tag'));

        $condition = new AppendCondition($query, null);

        self::assertSame($query, $condition->failIfEventsMatch);
        self::assertNull($condition->after);
    }

    public function test_properties_are_readonly(): void
    {
        $query = Query::fromItems(QueryItem::create(eventTypes: 'TestType'));
        $position = SequencePosition::fromInteger(5);

        $condition = new AppendCondition($query, $position);

        self::assertSame($query, $condition->failIfEventsMatch);
        self::assertSame($position, $condition->after);
    }
}

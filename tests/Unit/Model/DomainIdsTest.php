<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Model;

use InvalidArgumentException;
use Wwwision\DCBEventStore\Model\DomainId;
use Wwwision\DCBEventStore\Model\DomainIds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function json_decode;
use function json_encode;

#[CoversClass(DomainIds::class)]
final class DomainIdsTest extends TestCase
{

    public function test_single_creates_instance_with_single_id(): void
    {
        $ids = DomainIds::single('someKey', 'someId');
        self::assertDomainIdsMatch(['someKey' => 'someId'], $ids);
    }

    public function test_the_same_key_cant_be_used_twice(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $domainId = new class implements DomainId {
            public function key(): string {
                return 'someKey';
            }

            public function value(): string {
                return 'someValue';
            }
        };
        DomainIds::fromArray([$domainId, $domainId]);
    }

    public static function dataProvider_intersects(): iterable
    {
        yield ['ids1' => ['foo' => 'bar'], 'ids2' => ['foo' => 'bar'], 'expectedResult' => true];
        yield ['ids1' => ['foo' => 'bar', 'baz' => 'foos'], 'ids2' => ['foo' => 'bar'], 'expectedResult' => true];
        yield ['ids1' => ['foo' => 'bar', 'baz' => 'foos'], 'ids2' => ['baz' => 'foos'], 'expectedResult' => true];
        yield ['ids1' => ['foo' => 'bar', 'baz' => 'foos'], 'ids2' => ['foo' => 'bar', 'baz' => 'foos'], 'expectedResult' => true];
        yield ['ids1' => ['foo' => 'bar', 'baz' => 'foos'], 'ids2' => ['foo' => 'bar', 'baz' => 'other'], 'expectedResult' => true];
        yield ['ids1' => ['foo' => 'bar', 'baz' => 'foos'], 'ids2' => ['foo' => 'other', 'baz' => 'foos'], 'expectedResult' => true];

        yield ['ids1' => ['foo' => 'bar'], 'ids2' => ['foo' => 'bar2'], 'expectedResult' => false];
        yield ['ids1' => ['foo' => 'bar'], 'ids2' => ['bar' => 'bar'], 'expectedResult' => false];
        yield ['ids1' => ['foo' => 'bar', 'baz' => 'foos'], 'ids2' => ['foo' => 'other', 'baz' => 'other'], 'expectedResult' => false];
    }

    /**
     * @dataProvider dataProvider_intersects
     */
    public function test_intersects(array $ids1, array $ids2, bool $expectedResult): void
    {
        if ($expectedResult) {
            self::assertTrue(DomainIds::fromArray($ids1)->intersects(DomainIds::fromArray($ids2)));
        } else {
            self::assertFalse(DomainIds::fromArray($ids1)->intersects(DomainIds::fromArray($ids2)));
        }
    }

    public static function dataProvider_equals(): iterable
    {
        yield ['ids1' => [], 'ids2' => [], 'expectedResult' => true];
        yield ['ids1' => ['foo' => 'bar'], 'ids2' => ['foo' => 'bar'], 'expectedResult' => true];
        yield ['ids1' => ['foo' => 'bar', 'bar' => 'baz'], 'ids2' => ['bar' => 'baz', 'foo' => 'bar'], 'expectedResult' => true];
//
//        yield ['ids1' => [], 'ids2' => ['foo' => 'bar'], 'expectedResult' => false];
//        yield ['ids1' => ['foo' => 'bar'], 'ids2' => ['foo' => 'bar2'], 'expectedResult' => false];
//        yield ['ids1' => ['foo' => 'bar'], 'ids2' => ['bar' => 'bar'], 'expectedResult' => false];
//        yield ['ids1' => ['foo' => 'bar', 'baz' => 'foos'], 'ids2' => ['foo' => 'other', 'baz' => 'other'], 'expectedResult' => false];
    }

    /**
     * @dataProvider dataProvider_equals
     */
    public function test_equals(array $ids1, array $ids2, bool $expectedResult): void
    {
        if ($expectedResult) {
            self::assertTrue(DomainIds::fromArray($ids1)->equals(DomainIds::fromArray($ids2)));
        } else {
            self::assertFalse(DomainIds::fromArray($ids1)->equals(DomainIds::fromArray($ids2)));
        }
    }

    public function test_merge_fails_if_two_domain_ids_contain_different_values_for_the_same_key(): void
    {
        $ids1 = DomainIds::fromArray(['foo' => 'bar', 'bar' => 'baz']);
        $ids2 = DomainIds::fromArray(['foo' => 'bar', 'bar' => 'not_baz']);

        $this->expectException(InvalidArgumentException::class);
        $ids1->merge($ids2);
    }

    public function test_merge_returns_same_instance_if_values_are_equal(): void
    {
        $ids1 = DomainIds::single('foo', 'bar');
        $ids2 = DomainIds::single('foo', 'bar');

        self::assertSame($ids1, $ids1->merge($ids2));
    }

    public static function dataProvider_merge(): iterable
    {
        yield ['ids1' => [], 'ids2' => [], 'expectedResult' => []];
        yield ['ids1' => ['foo' => 'bar'], 'ids2' => ['foo' => 'bar'], 'expectedResult' => ['foo' => 'bar']];
        yield ['ids1' => ['foo' => 'bar', 'bar' => 'baz'], 'ids2' => ['bar' => 'baz'], 'expectedResult' => ['foo' => 'bar', 'bar' => 'baz']];
        yield ['ids1' => ['foo' => 'bar'], 'ids2' => ['bar' => 'baz'], 'expectedResult' => ['foo' => 'bar', 'bar' => 'baz']];
    }

    /**
     * @dataProvider dataProvider_merge
     */
    public function test_merge(array $ids1, array $ids2, array $expectedResult): void
    {
        self::assertDomainIdsMatch($expectedResult, DomainIds::fromArray($ids1)->merge(DomainIds::fromArray($ids2)));
    }

    // --------------------------------------

    private static function assertDomainIdsMatch(array $expected, DomainIds $actual): void
    {
        $actualArray = json_decode(json_encode($actual, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expected, $actualArray);
    }
}
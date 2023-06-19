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
use const JSON_THROW_ON_ERROR;

#[CoversClass(DomainIds::class)]
final class DomainIdsTest extends TestCase
{

    public function test_single_creates_instance_with_single_id(): void
    {
        $ids = DomainIds::single('someKey', 'someId');
        self::assertDomainIdsMatch([['someKey' => 'someId']], $ids);
    }

    public function test_create_merges_repeating_key_and_value_pairs(): void
    {
        $domainId = self::mockDomainId('someKey', 'someValue');
        self::assertDomainIdsMatch([['someKey' => 'someValue']], DomainIds::create($domainId, $domainId, $domainId));
    }

    public static function dataProvider_invalidKeys(): iterable
    {
        yield [123];
        yield [true];
        yield ['UpperCase'];
        yield ['lowerCaseButTooooLong'];
        yield ['späcialCharacters'];
    }

    /**
     * @dataProvider dataProvider_invalidKeys
     */
    public function test_fromArray_fails_if_specified_key_is_not_valid($key): void
    {
        $this->expectException(InvalidArgumentException::class);
        DomainIds::fromArray([[$key => 'bar']]);
    }

    public static function dataProvider_invalidValues(): iterable
    {
        yield [123];
        yield [true];
        yield ['UpperCase'];
        yield ['lowerCaseButTooooLong'];
        yield ['späcialCharacters'];
    }

    /**
     * @dataProvider dataProvider_invalidValues
     */
    public function test_fromArray_fails_if_specified_value_is_not_valid($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        DomainIds::fromArray([['key' => $value]]);
    }

    public function test_fromArray_merges_repeating_key_and_value_pairs(): void
    {
        $domainId = self::mockDomainId('someKey', 'someValue');
        self::assertDomainIdsMatch([['someKey' => 'someValue']], DomainIds::fromArray([$domainId, $domainId]));
    }

    public function test_fromJson_fails_if_value_is_not_valid_JSON(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DomainIds::fromJson('not-json');
    }

    public function test_fromJson_fails_if_value_is_no_JSON_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DomainIds::fromJson('false');
    }

    public function test_fromJson_sorts_values_and_removes_duplicates(): void
    {
        $domainIds = DomainIds::fromJson('[{"foo": "bar"}, {"bar": "foos"}, {"foo": "bar"}, {"foo": "baz"}]');
        self::assertDomainIdsMatch([['bar' => 'foos'], ['foo' => 'bar'], ['foo' => 'baz']], $domainIds);
    }

    public function test_intersects_allows_checking_single_domainIds(): void
    {
        $ids = DomainIds::fromArray([['foo' => 'bar'], ['baz' => 'foos']]);

        self::assertTrue($ids->intersects(self::mockDomainId('baz', 'foos')));
        self::assertFalse($ids->intersects(self::mockDomainId('foo', 'foos')));
    }


    public static function dataProvider_intersects(): iterable
    {
        yield ['ids1' => [['foo' => 'bar']], 'ids2' => [['foo' => 'bar']], 'expectedResult' => true];
        yield ['ids1' => [['foo' => 'bar'], ['baz' => 'foos']], 'ids2' => [['foo' => 'bar']], 'expectedResult' => true];
        yield ['ids1' => [['foo' => 'bar'], ['baz' => 'foos']], 'ids2' => [['baz' => 'foos']], 'expectedResult' => true];
        yield ['ids1' => [['foo' => 'bar'], ['baz' => 'foos']], 'ids2' => [['foo' => 'bar'], ['baz' => 'foos']], 'expectedResult' => true];
        yield ['ids1' => [['foo' => 'bar'], ['baz' => 'foos']], 'ids2' => [['foo' => 'bar'], ['baz' => 'other']], 'expectedResult' => true];
        yield ['ids1' => [['foo' => 'bar'], ['baz' => 'foos']], 'ids2' => [['foo' => 'other'], ['baz' => 'foos']], 'expectedResult' => true];

        yield ['ids1' => [['foo' => 'bar']], 'ids2' => [['foo' => 'bar2']], 'expectedResult' => false];
        yield ['ids1' => [['foo' => 'bar']], 'ids2' => [['bar' => 'bar']], 'expectedResult' => false];
        yield ['ids1' => [['foo' => 'bar'], ['baz' => 'foos']], 'ids2' => [['foo' => 'other'], ['baz' => 'other']], 'expectedResult' => false];
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
        yield ['ids1' => [['foo' => 'bar']], 'ids2' => [['foo' => 'bar']], 'expectedResult' => true];
        yield ['ids1' => [['foo' => 'bar'], ['bar' => 'baz']], 'ids2' => [['bar' => 'baz'], ['foo' => 'bar']], 'expectedResult' => true];

        yield ['ids1' => [['foo' => 'bar']], 'ids2' => [['foo' => 'bar2']], 'expectedResult' => false];
        yield ['ids1' => [['foo' => 'bar']], 'ids2' => [['bar' => 'bar']], 'expectedResult' => false];
        yield ['ids1' => [['foo' => 'bar'], ['baz' => 'foos']], 'ids2' => [['foo' => 'other'], ['baz' => 'other']], 'expectedResult' => false];
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

    public function test_merge_allows_two_domain_ids_that_contain_different_values_for_the_same_key(): void
    {
        $ids1 = DomainIds::fromArray([['foo' => 'bar'], ['bar' => 'baz']]);
        $ids2 = DomainIds::fromArray([['foo' => 'bar'], ['bar' => 'not_baz']]);

        self::assertDomainIdsMatch([['bar' => 'baz'], ['bar' => 'not_baz'], ['foo' => 'bar']], $ids1->merge($ids2));
    }

    public function test_merge_returns_same_instance_if_values_are_equal(): void
    {
        $ids1 = DomainIds::single('foo', 'bar');
        $ids2 = DomainIds::single('foo', 'bar');

        self::assertSame($ids1, $ids1->merge($ids2));
    }

    public function test_merge_allows_merging_single_domainIds(): void
    {
        $ids1 = DomainIds::single('foo', 'bar');
        $ids2 = self::mockDomainId('foo', 'baz');

        self::assertDomainIdsMatch([['foo' => 'bar'], ['foo' => 'baz']], $ids1->merge($ids2));
    }

    public static function dataProvider_merge(): iterable
    {
        yield ['ids1' => [['foo' => 'bar']], 'ids2' => [['foo' => 'bar']], 'expectedResult' => [['foo' => 'bar']]];
        yield ['ids1' => [['foo' => 'bar'], ['bar' => 'baz']], 'ids2' => [['bar' => 'baz']], 'expectedResult' => [['bar' => 'baz'], ['foo' => 'bar']]];
        yield ['ids1' => [['foo' => 'bar']], 'ids2' => [['bar' => 'baz']], 'expectedResult' => [['bar' => 'baz'], ['foo' => 'bar']]];
    }

    /**
     * @dataProvider dataProvider_merge
     */
    public function test_merge(array $ids1, array $ids2, array $expectedResult): void
    {
        self::assertDomainIdsMatch($expectedResult, DomainIds::fromArray($ids1)->merge(DomainIds::fromArray($ids2)));
    }

    public function test_contains_allows_checking_single_domainIds(): void
    {
        $ids = DomainIds::fromArray([['foo' => 'bar'], ['baz' => 'foos']]);

        self::assertTrue($ids->contains(self::mockDomainId('baz', 'foos')));
        self::assertFalse($ids->contains(self::mockDomainId('foo', 'foos')));
    }

    public static function dataProvider_contains(): iterable
    {
        yield ['ids' => [['foo' => 'bar']], 'key' => 'foo', 'value' => 'bar', 'expectedResult' => true];
        yield ['ids' => [['foo' => 'bar'], ['bar' => 'baz']], 'key' => 'bar', 'value' => 'baz', 'expectedResult' => true];

        yield ['ids' => [['foo' => 'bar']], 'key' => 'foo', 'value' => 'bar2', 'expectedResult' => false];
        yield ['ids' => [['foo' => 'bar']], 'key' => 'bar', 'value' => 'bar', 'expectedResult' => false];
        yield ['ids' => [['foo' => 'bar'], ['baz' => 'foos']], 'key' => 'notFoo', 'value' => 'notBar', 'expectedResult' => false];
    }

    /**
     * @dataProvider dataProvider_contains
     */
    public function test_contains(array $ids, string $key, string $value, bool $expectedResult): void
    {
        if ($expectedResult) {
            self::assertTrue(DomainIds::fromArray($ids)->contains($key, $value));
        } else {
            self::assertFalse(DomainIds::fromArray($ids)->contains($key, $value));
        }
    }

    public function test_serialized_format(): void
    {
        $domainIds = DomainIds::fromJson('[{"foo": "bar"}, {"bar": "foos"}, {"foo": "bar"}, {"foo": "baz"}]');
        self::assertJsonStringEqualsJsonString('[{"bar": "foos"}, {"foo": "bar"}, {"foo": "baz"}]', json_encode($domainIds));
    }

    // --------------------------------------

    private static function mockDomainId(string $key, string $value): DomainId
    {
        return new class ($key, $value) implements DomainId {
            public function __construct(private readonly string $key, private readonly string $value) {}
            public function key(): string { return $this->key; }
            public function value(): string { return $this->value; }
        };
    }
    private static function assertDomainIdsMatch(array $expected, DomainIds $actual): void
    {
        self::assertSame($expected, $actual->toArray());
    }
}
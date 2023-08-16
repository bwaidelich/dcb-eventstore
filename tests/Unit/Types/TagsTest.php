<?php
declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Types;

use InvalidArgumentException;
use Wwwision\DCBEventStore\Types\Tag;
use Wwwision\DCBEventStore\Types\Tags;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function json_decode;
use function json_encode;
use const JSON_THROW_ON_ERROR;

#[CoversClass(Tags::class)]
#[CoversClass(Tag::class)]
final class TagsTest extends TestCase
{

    public function test_single_creates_instance_with_single_id(): void
    {
        $ids = Tags::create(Tag::create('someKey', 'someId'));
        self::assertTagsMatch(['someKey:someId'], $ids);
    }

    public function test_create_merges_repeating_key_and_value_pairs(): void
    {
        $Tag = Tag::create('someKey', 'someValue');
        self::assertTagsMatch(['someKey:someValue'], Tags::create($Tag, $Tag, $Tag));
    }

    public static function dataProvider_invalidKeys(): iterable
    {
        yield [123];
        yield [true];
        yield ['validCharactersButExactlyOneCharacterToooooooooLong'];
        yield ['späcialCharacters'];
    }

    /**
     * @dataProvider dataProvider_invalidKeys
     */
    public function test_fromArray_fails_if_specified_key_is_not_valid($key): void
    {
        $this->expectException(InvalidArgumentException::class);
        Tags::fromArray([['key' => $key]]);
    }

    public static function dataProvider_invalidValues(): iterable
    {
        yield [123];
        yield [true];
        yield ['validCharactersButExactlyOneCharacterToooooooooLong'];
        yield ['späcialCharacters'];
    }

    /**
     * @dataProvider dataProvider_invalidValues
     */
    public function test_fromArray_fails_if_specified_value_is_not_valid($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Tags::fromArray([['key' => 'tag', 'value' => $value]]);
    }

    public function test_fromArray_merges_repeating_key_and_value_pairs(): void
    {
        $tag = Tag::create('someKey', 'someValue');
        self::assertTagsMatch(['someKey:someValue'], Tags::fromArray([$tag, $tag]));
    }

    public function test_fromJson_fails_if_value_is_not_valid_JSON(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Tags::fromJson('not-json');
    }

    public function test_fromJson_fails_if_value_is_no_JSON_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Tags::fromJson('false');
    }

    public function test_fromJson_sorts_values_and_removes_duplicates(): void
    {
        $tags = Tags::fromJson('[{"key": "foo", "value": "bar"}, {"key": "bar", "value": "foos"}, {"key": "foo", "value": "bar"}, {"key": "foo", "value": "baz"}]');
        self::assertTagsMatch(['bar:foos', 'foo:bar', 'foo:baz'], $tags);
    }

    public function test_intersects_allows_checking_single_tags(): void
    {
        $ids = Tags::fromArray(['foo:bar', 'baz:foos']);

        self::assertTrue($ids->intersect(Tag::create('baz', 'foos')));
        self::assertFalse($ids->intersect(Tag::create('foo', 'foos')));
    }


    public static function dataProvider_intersects(): iterable
    {
        yield ['ids1' => ['foo:bar'], 'ids2' => ['foo:bar'], 'expectedResult' => true];
        yield ['ids1' => ['foo:bar', 'baz:foos'], 'ids2' => ['foo:bar'], 'expectedResult' => true];
        yield ['ids1' => ['foo:bar', 'baz:foos'], 'ids2' => ['baz:foos'], 'expectedResult' => true];
        yield ['ids1' => ['foo:bar', 'baz:foos'], 'ids2' => ['foo:bar', 'baz:foos'], 'expectedResult' => true];
        yield ['ids1' => ['foo:bar', 'baz:foos'], 'ids2' => ['foo:bar', 'baz:other'], 'expectedResult' => true];
        yield ['ids1' => ['foo:bar', 'baz:foos'], 'ids2' => ['foo:other', 'baz:foos'], 'expectedResult' => true];

        yield ['ids1' => ['foo:bar'], 'ids2' => ['foo:bar2'], 'expectedResult' => false];
        yield ['ids1' => ['foo:bar'], 'ids2' => ['bar:bar'], 'expectedResult' => false];
        yield ['ids1' => ['foo:bar', 'baz:foos'], 'ids2' => ['foo:other', 'baz:other'], 'expectedResult' => false];
    }

    /**
     * @dataProvider dataProvider_intersects
     */
    public function test_intersects(array $ids1, array $ids2, bool $expectedResult): void
    {
        if ($expectedResult) {
            self::assertTrue(Tags::fromArray($ids1)->intersect(Tags::fromArray($ids2)));
        } else {
            self::assertFalse(Tags::fromArray($ids1)->intersect(Tags::fromArray($ids2)));
        }
    }

    public static function dataProvider_equals(): iterable
    {
        yield ['ids1' => ['foo:bar'], 'ids2' => ['foo:bar'], 'expectedResult' => true];
        yield ['ids1' => ['foo:bar', 'bar:baz'], 'ids2' => ['bar:baz', 'foo:bar'], 'expectedResult' => true];

        yield ['ids1' => ['foo:bar'], 'ids2' => ['foo:bar2'], 'expectedResult' => false];
        yield ['ids1' => ['foo:bar'], 'ids2' => ['bar:bar'], 'expectedResult' => false];
        yield ['ids1' => ['foo:bar', 'baz:foos'], 'ids2' => ['foo:other', 'baz:other'], 'expectedResult' => false];
    }

    /**
     * @dataProvider dataProvider_equals
     */
    public function test_equals(array $ids1, array $ids2, bool $expectedResult): void
    {
        if ($expectedResult) {
            self::assertTrue(Tags::fromArray($ids1)->equals(Tags::fromArray($ids2)));
        } else {
            self::assertFalse(Tags::fromArray($ids1)->equals(Tags::fromArray($ids2)));
        }
    }

    public function test_merge_allows_two_tags_that_contain_different_values_for_the_same_key(): void
    {
        $ids1 = Tags::fromArray(['foo:bar', 'bar:baz']);
        $ids2 = Tags::fromArray(['foo:bar', 'bar:not_baz']);

        self::assertTagsMatch(['bar:baz', 'bar:not_baz', 'foo:bar'], $ids1->merge($ids2));
    }

    public function test_merge_removes_duplicates(): void
    {
        $ids1 = Tags::fromArray(['foo:bar', 'bar:baz']);
        $ids2 = Tags::fromArray(['bar:not_baz', 'foo:bar']);

        self::assertTagsMatch(['bar:baz', 'bar:not_baz', 'foo:bar'], $ids1->merge($ids2));
    }

    public function test_merge_returns_same_instance_if_values_are_equal(): void
    {
        $ids1 = Tags::create(Tag::create('foo', 'bar'));
        $ids2 = Tags::create(Tag::create('foo', 'bar'));

        self::assertSame($ids1, $ids1->merge($ids2));
    }

    public function test_merge_allows_merging_single_tags(): void
    {
        $ids1 = Tags::create(Tag::create('foo', 'bar'));
        $ids2 = Tag::create('foo', 'baz');

        self::assertTagsMatch(['foo:bar', 'foo:baz'], $ids1->merge($ids2));
    }

    public static function dataProvider_merge(): iterable
    {
        yield ['ids1' => ['foo:bar'], 'ids2' => ['foo:bar'], 'expectedResult' => ['foo:bar']];
        yield ['ids1' => ['foo:bar', 'bar:baz'], 'ids2' => ['bar:baz'], 'expectedResult' => ['bar:baz', 'foo:bar']];
        yield ['ids1' => ['foo:bar'], 'ids2' => ['bar:baz'], 'expectedResult' => ['bar:baz', 'foo:bar']];
    }

    /**
     * @dataProvider dataProvider_merge
     */
    public function test_merge(array $ids1, array $ids2, array $expectedResult): void
    {
        self::assertTagsMatch($expectedResult, Tags::fromArray($ids1)->merge(Tags::fromArray($ids2)));
    }

    public function test_contains_allows_checking_single_tags(): void
    {
        $ids = Tags::fromArray(['foo:bar', 'baz:foos']);

        self::assertTrue($ids->contain(Tag::create('baz', 'foos')));
        self::assertFalse($ids->contain(Tag::create('foo', 'foos')));
    }

    public static function dataProvider_contains(): iterable
    {
        yield ['ids' => ['foo:bar'], 'key' => 'foo', 'value' => 'bar', 'expectedResult' => true];
        yield ['ids' => ['foo:bar', 'bar:baz'], 'key' => 'bar', 'value' => 'baz', 'expectedResult' => true];

        yield ['ids' => ['foo:bar'], 'key' => 'key', 'value' => 'bar2', 'expectedResult' => false];
        yield ['ids' => ['foo:bar'], 'key' => 'bar', 'value' => 'bar', 'expectedResult' => false];
        yield ['ids' => ['foo:bar', 'baz:foos'], 'key' => 'notFoo', 'value' => 'notBar', 'expectedResult' => false];
    }

    /**
     * @dataProvider dataProvider_contains
     */
    public function test_contains(array $ids, string $key, string $value, bool $expectedResult): void
    {
        if ($expectedResult) {
            self::assertTrue(Tags::fromArray($ids)->contain(Tag::create($key, $value)));
        } else {
            self::assertFalse(Tags::fromArray($ids)->contain(Tag::create($key, $value)));
        }
    }

    public function test_serialized_format(): void
    {
        $tags = Tags::fromJson('["foo:bar", "bar:foos", "foo:bar", "foo:baz"]');
        self::assertJsonStringEqualsJsonString('[{"key": "bar", "value": "foos"}, {"key": "foo", "value": "bar"}, {"key": "foo", "value": "baz"}]', json_encode($tags));
    }

    // --------------------------------------

    private static function assertTagsMatch(array $expected, Tags $actual): void
    {
        self::assertSame($expected, $actual->toSimpleArray());
    }
}
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
        $ids = Tags::create(Tag::fromString('someKey:someId'));
        self::assertTagsMatch(['someKey:someId'], $ids);
    }

    public function test_create_merges_repeating_key_and_value_pairs(): void
    {
        $Tag = Tag::fromString('someKey:someValue');
        self::assertTagsMatch(['someKey:someValue'], Tags::create($Tag, $Tag, $Tag));
    }

    public static function dataProvider_invalidValues(): iterable
    {
        yield [123];
        yield [true];
        yield ['validCharactersButExactlyOneCharacterToooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooooLong'];
        yield ['späcialCharacters'];
    }

    /**
     * @dataProvider dataProvider_invalidValues
     */
    public function test_fromArray_fails_if_specified_value_is_not_valid($value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Tags::fromArray([$value]);
    }

    public function test_fromArray_merges_repeating_tags(): void
    {
        $tag = Tag::fromString('someKey:someValue');
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
        $tags = Tags::fromJson('["foo:bar", "bar:foos", "foo:bar", "foo:baz"]');
        self::assertTagsMatch(['bar:foos', 'foo:bar', 'foo:baz'], $tags);
    }

    public function test_single_fails_if_key_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Tags::single('invälid', 'some-value');
    }

    public function test_single_fails_if_value_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Tags::single('invälid');
    }

    public function test_single_returns_instance_with_single_tag(): void
    {
        self::assertTagsMatch(['foo:bar'], Tags::single('foo:bar'));
    }

    public function test_intersects_allows_checking_single_tags(): void
    {
        $ids = Tags::fromArray(['foo:bar', 'baz:foos']);

        self::assertTrue($ids->intersect(Tag::fromString('baz:foos')));
        self::assertFalse($ids->intersect(Tag::fromString('foo:foos')));
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
        $ids1 = Tags::create(Tag::fromString('foo:bar'));
        $ids2 = Tags::create(Tag::fromString('foo:bar'));

        self::assertSame($ids1, $ids1->merge($ids2));
    }

    public function test_merge_allows_merging_single_tags(): void
    {
        $ids1 = Tags::create(Tag::fromString('foo:bar'));
        $ids2 = Tag::fromString('foo:baz');

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

        self::assertTrue($ids->contain(Tag::fromString('baz:foos')));
        self::assertFalse($ids->contain(Tag::fromString('foo:foos')));
    }

    public static function dataProvider_contains(): iterable
    {
        yield ['tags' => ['foo:bar'], 'tag' => 'foo:bar', 'expectedResult' => true];
        yield ['tags' => ['foo:bar', 'bar:baz'], 'tag' => 'bar:baz', 'expectedResult' => true];

        yield ['tags' => ['foo:bar'], 'tag' => 'key:bar2', 'expectedResult' => false];
        yield ['tags' => ['foo:bar'], 'tag' => 'bar:bar', 'expectedResult' => false];
        yield ['tags' => ['foo:bar', 'baz:foos'], 'bar' => 'notFoo:notBar', 'expectedResult' => false];
    }

    /**
     * @dataProvider dataProvider_contains
     */
    public function test_contains(array $tags, string $tag, bool $expectedResult): void
    {
        if ($expectedResult) {
            self::assertTrue(Tags::fromArray($tags)->contain(Tag::fromString($tag)));
        } else {
            self::assertFalse(Tags::fromArray($tags)->contain(Tag::fromString($tag)));
        }
    }

    public function test_serialized_format(): void
    {
        $tags = Tags::fromJson('["foo:bar", "bar:foos", "foo:bar", "foo:baz"]');
        self::assertJsonStringEqualsJsonString('["bar:foos", "foo:bar", "foo:baz"]', json_encode($tags));
    }

    // --------------------------------------

    private static function assertTagsMatch(array $expected, Tags $actual): void
    {
        self::assertSame($expected, $actual->toStrings());
    }
}

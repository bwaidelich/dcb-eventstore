<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Tests\Unit\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Event\Tag;
use Wwwision\DCBEventStore\Event\Tags;

use function json_encode;

#[CoversClass(Tag::class)]
#[CoversClass(Tags::class)]
final class TagTest extends TestCase
{
    public function test_fromString_creates_valid_tag(): void
    {
        $tag = Tag::fromString('product:sku123');

        self::assertSame('product:sku123', $tag->value);
    }

    #[DataProvider('validTagProvider')]
    public function test_fromString_accepts_valid_tags(string $value): void
    {
        $tag = Tag::fromString($value);

        self::assertSame($value, $tag->value);
    }

    /**
     * @return array<mixed>
     */
    public static function validTagProvider(): array
    {
        return [
            'alphanumeric' => ['abc123'],
            'with-dashes' => ['product-sku'],
            'with_underscores' => ['user_id'],
            'with:colons' => ['entity:id:123'],
            'mixed' => ['product:sku-123_v2'],
            'single-char' => ['a'],
            'max-length' => [str_repeat('a', 150)],
        ];
    }

    #[DataProvider('invalidTagProvider')]
    public function test_fromString_rejects_invalid_tags(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Tag::fromString($value);
    }

    /**
     * @return array<mixed>
     */
    public static function invalidTagProvider(): array
    {
        return [
            'empty' => [''],
            'with-spaces' => ['product sku'],
            'with-special-chars' => ['product@123'],
            'with-dots' => ['product.sku'],
            'with-slashes' => ['product/sku'],
            'too-long' => [str_repeat('a', 151)],
            'with-newline' => ["product\nsku"],
            'with-tab' => ["product\tsku"],
        ];
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $tag1 = Tag::fromString('product:123');
        $tag2 = Tag::fromString('product:123');

        self::assertTrue($tag1->equals($tag2));
    }

    public function test_equals_returns_false_for_different_value(): void
    {
        $tag1 = Tag::fromString('product:123');
        $tag2 = Tag::fromString('product:456');

        self::assertFalse($tag1->equals($tag2));
    }

    public function test_equals_is_case_sensitive(): void
    {
        $tag1 = Tag::fromString('Product:123');
        $tag2 = Tag::fromString('product:123');

        self::assertFalse($tag1->equals($tag2));
    }

    public function test_merge_with_same_tag_returns_original(): void
    {
        $tag1 = Tag::fromString('product:123');
        $tag2 = Tag::fromString('product:123');

        $result = $tag1->merge($tag2);

        self::assertInstanceOf(Tag::class, $result);
        self::assertSame($tag1, $result);
    }

    public function test_merge_with_different_tag_returns_Tags(): void
    {
        $tag1 = Tag::fromString('product:123');
        $tag2 = Tag::fromString('user:456');

        $result = $tag1->merge($tag2);

        self::assertInstanceOf(Tags::class, $result);
    }

    public function test_merge_with_Tags_instance_returns_Tags(): void
    {
        $tag = Tag::fromString('product:123');
        $tags = Tags::fromArray(['user:456', 'order:789']);

        $result = $tag->merge($tags);

        self::assertInstanceOf(Tags::class, $result);
    }

    public function test_jsonSerialize_returns_string_value(): void
    {
        $tag = Tag::fromString('product:123');

        self::assertSame('product:123', $tag->jsonSerialize());
    }

    public function test_json_encode_produces_string(): void
    {
        $tag = Tag::fromString('product:123');

        $json = json_encode($tag);

        self::assertSame('"product:123"', $json);
    }
}

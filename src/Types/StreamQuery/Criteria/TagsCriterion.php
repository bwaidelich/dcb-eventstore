<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore\Types\StreamQuery\Criteria;

use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\StreamQuery\Criterion;
use Wwwision\DCBEventStore\Types\Tags;

final readonly class TagsCriterion implements Criterion
{
    public function __construct(
        public Tags $tags,
    ) {
    }

    public function matches(Event $event): bool
    {
        return $this->tags->intersect($event->tags);
    }
}

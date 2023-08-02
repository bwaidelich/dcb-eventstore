<?php

declare(strict_types=1);

namespace Wwwision\DCBEventStore;

/**
 * Contract for a setup-able event store implementation
 */
interface Setupable
{
    /**
     * Some adapters require setup (e.g. to create required database tables and/or establish/check connection)
     */
    public function setup(): void;
}

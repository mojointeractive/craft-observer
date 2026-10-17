<?php

declare(strict_types=1);

namespace mojo\observer\vitals;

/**
 * One area of the vitals payload. A provider whose dependency is missing (a plugin that is not
 * installed, the craftly doctor on a plain Craft site) says so through available(), and the
 * payload simply has no entry for it.
 */
interface Provider
{
    /** The payload key, e.g. "updates". */
    public function key(): string;

    public function available(): bool;

    /** @return array<string, mixed> */
    public function collect(): array;
}

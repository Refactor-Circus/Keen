<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Tests\Fixtures\Screens;

/**
 * An Atrium release from before the screen-extensions registry: it takes
 * plugins, but has no `screens()`.
 */
final class PreRegistryAtrium
{
    public function plugin(object|string $plugin): static
    {
        return $this;
    }
}

<?php

namespace PersianKit\Tests\Integration\Support;

/**
 * For a module subclass: its plugin counts as active or not, whatever this
 * site has loaded.
 */
trait FakesPluginState
{
    private bool $pluginActive = true;

    public function withPluginActive(bool $active): static
    {
        $this->pluginActive = $active;

        return $this;
    }

    public function requiredPlugins(): array
    {
        return array_map(
            fn (array $plugin): array => ['check' => fn (): bool => $this->pluginActive] + $plugin,
            parent::requiredPlugins()
        );
    }
}

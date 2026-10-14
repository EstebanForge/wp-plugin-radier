<?php

declare(strict_types=1);

namespace WpPluginRadier\Services;

use WpPluginRadier\PluginContext;

final class I18nService
{
    private bool $loaded = false;

    public function __construct(
        private readonly PluginContext $context,
    ) {}

    public function loadTextdomain(): bool
    {
        $pluginRelPath = dirname(plugin_basename($this->context->mainFile)) . '/languages';

        $result = load_plugin_textdomain($this->context->textDomain, false, $pluginRelPath);

        $this->loaded = $result;

        return $result;
    }

    public function isLoaded(): bool
    {
        return $this->loaded;
    }
}

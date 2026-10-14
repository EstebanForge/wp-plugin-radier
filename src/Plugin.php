<?php

declare(strict_types=1);

namespace WpPluginRadier;

use WpPluginRadier\Services\ExampleService;
use WpPluginRadier\Services\I18nService;

final class Plugin
{
    private static ?self $instance = null;

    /** @var array<class-string, object> */
    private array $services = [];

    private bool $booted = false;

    /**
     * Pure constructor: zero side effects, no hooks registered, no services booted.
     */
    public function __construct(
        private readonly PluginContext $context,
    ) {}

    public static function init(PluginContext $context): self
    {
        if (self::$instance === null) {
            self::$instance = new self($context);
            self::$instance->boot();
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;
        $this->registerServices();
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function getContext(): PluginContext
    {
        return $this->context;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    public function get(string $class): ?object
    {
        $service = $this->services[$class] ?? null;

        return $service instanceof $class ? $service : null;
    }

    private function registerServices(): void
    {
        $i18n = new I18nService($this->context);
        $this->services[I18nService::class] = $i18n;

        $example = new ExampleService($this->context);
        $this->services[ExampleService::class] = $example;

        // Note: WordPress 6.7+ triggers _doing_it_wrong if translation functions run before 'init'.
        add_action('init', [$i18n, 'loadTextdomain']);
        add_action('init', [$example, 'registerPostType']);
        add_filter('the_content', [$example, 'filterContent']);
    }
}

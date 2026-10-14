<?php

declare(strict_types=1);

namespace WpPluginRadier\Tests\Unit;

use Brain\Monkey;
use Mockery;
use WpPluginRadier\Plugin;
use WpPluginRadier\PluginContext;
use WpPluginRadier\Services\ExampleService;
use WpPluginRadier\Services\I18nService;

it('has side-effect-free constructor without auto-booting', function () {
    Monkey\Actions\expectAdded('plugins_loaded')->never();
    Monkey\Actions\expectAdded('init')->never();
    Monkey\Filters\expectAdded('the_content')->never();

    $context = new PluginContext('/mock/file.php', '/mock/', 'https://example.com/');

    $plugin = new Plugin($context);

    expect($plugin->isBooted())->toBeFalse()
        ->and($plugin->getContext())->toBe($context);
});

it('initializes as singleton via init() and boots', function () {
    $context = PluginContext::fromFile(__DIR__ . '/../../wp-plugin-radier.php');
    $plugin = Plugin::init($context);

    expect($plugin)->toBeInstanceOf(Plugin::class)
        ->and($plugin->isBooted())->toBeTrue()
        ->and(Plugin::init($context))->toBe($plugin);
});

it('resets singleton instance', function () {
    $context = PluginContext::fromFile(__DIR__ . '/../../wp-plugin-radier.php');
    $plugin1 = Plugin::init($context);
    Plugin::reset();
    $plugin2 = Plugin::init($context);

    expect($plugin1)->not()->toBe($plugin2);
});

it('registers hooks with WordPress on boot', function () {
    $context = PluginContext::fromFile(__DIR__ . '/../../wp-plugin-radier.php');
    Plugin::init($context);

    expect(Monkey\Actions\has('init'))->toBeTrue()
        ->and(Monkey\Filters\has('the_content'))->toBeTrue();
});

it('resolves registered services via FQCN with full type safety', function () {
    $context = PluginContext::fromFile(__DIR__ . '/../../wp-plugin-radier.php');
    $plugin = Plugin::init($context);

    expect($plugin->get(ExampleService::class))->toBeInstanceOf(ExampleService::class)
        ->and($plugin->get(I18nService::class))->toBeInstanceOf(I18nService::class)
        ->and($plugin->get(\stdClass::class))->toBeNull();
});

it('wires the resolved service instances to hooks at default priority', function () {
    $context = PluginContext::fromFile(__DIR__ . '/../../wp-plugin-radier.php');
    $plugin = Plugin::init($context);

    $example = $plugin->get(ExampleService::class);
    $i18n = $plugin->get(I18nService::class);

    assert($example instanceof ExampleService);
    assert($i18n instanceof I18nService);

    expect(Monkey\Actions\has('init', [$i18n, 'loadTextdomain']))->toBe(10)
        ->and(Monkey\Actions\has('init', [$example, 'registerPostType']))->toBe(10)
        ->and(Monkey\Filters\has('the_content', [$example, 'filterContent']))->toBe(10);
});

it('filters content through the wired WordPress filter callback', function () {
    $captured = null;

    Monkey\Filters\expectAdded('the_content')->once()->with(Mockery::on(
        function (mixed $callback) use (&$captured): bool {
            $captured = $callback;

            return is_callable($callback);
        }
    ));

    wp_test_state()['query_context'] = ['is_main_query' => true, 'in_the_loop' => true];

    Plugin::init(PluginContext::fromFile(__DIR__ . '/../../wp-plugin-radier.php'));

    expect($captured)->toBeCallable();

    $result = $captured('Original content');

    expect($result)->toContain('Original content')
        ->and($result)->toContain('<!-- Processed by WpPluginRadier -->');
});

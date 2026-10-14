<?php

declare(strict_types=1);

namespace WpPluginRadier\Tests\Unit;

use Brain\Monkey;
use Mockery;
use WpPluginRadier\Plugin;
use WpPluginRadier\PluginContext;
use WpPluginRadier\Services\ExampleService;
use WpPluginRadier\Services\I18nService;

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/wp-test/');
}

function entrypointFile(): string
{
    return dirname(__DIR__, 2) . '/wp-plugin-radier.php';
}

/**
 * Loads the entrypoint once per process. The require must happen inside a
 * test (never at file scope): file-scope hook registrations would leak into
 * whichever test runs next, because wp_test_reset() clears state per test.
 *
 * Brain Monkey owns the hook functions, so the plugins_loaded callback is
 * captured through an expectAdded() argument matcher instead of test state.
 */
function loadEntrypoint(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $loaded = true;

    $GLOBALS['radier_entrypoint'] = ['plugins_loaded' => null];

    Monkey\Actions\expectAdded('plugins_loaded')->once()->with(Mockery::on(
        function (mixed $callback): bool {
            $GLOBALS['radier_entrypoint']['plugins_loaded'] = $callback;

            return is_callable($callback);
        }
    ));

    require_once entrypointFile();

    $GLOBALS['radier_entrypoint']['activation'] = wp_test_state()['lifecycle_hooks']['activation'][0]['callback'] ?? null;
    $GLOBALS['radier_entrypoint']['activation_file'] = wp_test_state()['lifecycle_hooks']['activation'][0]['file'] ?? null;
    $GLOBALS['radier_entrypoint']['deactivation'] = wp_test_state()['lifecycle_hooks']['deactivation'][0]['callback'] ?? null;
    $GLOBALS['radier_entrypoint']['deactivation_file'] = wp_test_state()['lifecycle_hooks']['deactivation'][0]['file'] ?? null;
}

function bootPlugin(): Plugin
{
    loadEntrypoint();

    ($GLOBALS['radier_entrypoint']['plugins_loaded'])();

    return Plugin::init(PluginContext::fromFile(entrypointFile()));
}

it('parses the plugin version from the file header', function () {
    loadEntrypoint();

    $headerVersion = get_file_data(entrypointFile(), ['Version' => 'Version'])['Version'];

    expect($headerVersion)->not()->toBe('')
        ->and(defined('\\WP_PLUGIN_RADIER_VERSION'))->toBeTrue()
        ->and(constant('\\WP_PLUGIN_RADIER_VERSION'))->toBe($headerVersion);
});

it('registers lifecycle and bootstrap hooks at file scope', function () {
    loadEntrypoint();

    expect($GLOBALS['radier_entrypoint']['activation'])->toBeCallable()
        ->and($GLOBALS['radier_entrypoint']['activation_file'])->toBe(entrypointFile())
        ->and($GLOBALS['radier_entrypoint']['deactivation'])->toBeCallable()
        ->and($GLOBALS['radier_entrypoint']['deactivation_file'])->toBe(entrypointFile())
        ->and($GLOBALS['radier_entrypoint']['plugins_loaded'])->toBeCallable();
});

it('boots the plugin with all services when plugins_loaded fires', function () {
    $plugin = bootPlugin();

    expect($plugin->isBooted())->toBeTrue()
        ->and($plugin->get(I18nService::class))->toBeInstanceOf(I18nService::class)
        ->and($plugin->get(ExampleService::class))->toBeInstanceOf(ExampleService::class)
        ->toHaveRegisteredAction('init')
        ->toHaveRegisteredFilter('the_content');
});

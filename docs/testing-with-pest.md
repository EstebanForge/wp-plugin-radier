# Testing with Pest v4

This document explains how to write and execute tests using Pest v4 and Brain Monkey in this plugin scaffold.

## Overview

The test suite runs without installing or booting a WordPress database.
In-memory WordPress stubs in `tests/Pest.php` and the [Brain Monkey](https://github.com/Brain-WP/BrainMonkey) library emulate actions, filters, paths, post types, and translation loading.
This makes tests fast, deterministic, and isolated.

## Running Tests

Execute the test suite using Composer:

```bash
composer test
```

Or invoke the Pest binary directly:

```bash
./vendor/bin/pest
```

Run a specific test file:

```bash
./vendor/bin/pest tests/Unit/ExampleServiceTest.php
```

Filter tests by description:

```bash
./vendor/bin/pest --filter="filters content"
```

## Test Harness Ownership Split

Two systems emulate WordPress, with a strict split:

| Emulated API | Owned by |
|---|---|
| Hook API: `add_action`, `add_filter`, `do_action`, `apply_filters`, `did_action`, `has_action`, `remove_*`, `current_filter` | Brain Monkey (predefined at `setUp()`) |
| `trailingslashit`, `untrailingslashit`, `__return_*` helpers | Brain Monkey (predefined at `setUp()`) |
| `register_post_type` | Nobody predefined: create it on demand with `Functions\expect()` or `Functions\when()` |
| `plugin_dir_path`, `plugin_basename`, `plugins_url` | `tests/Pest.php` stubs |
| `load_plugin_textdomain` (mo_files-driven success/failure) | `tests/Pest.php` stubs |
| `register_activation_hook`, `register_deactivation_hook` | `tests/Pest.php` stubs |
| `__` (records translations), `get_file_data` (parses real headers) | `tests/Pest.php` stubs |
| `is_main_query`, `in_the_loop` (query_context-driven) | `tests/Pest.php` stubs |
| `is_multisite`, `esc_html` | `tests/Pest.php` stubs |

Brain Monkey's own definitions load inside `Brain\Monkey\setUp()` behind `function_exists` guards.
Patchwork (its function-redefinition engine) boots at that same moment, so a function defined earlier in `tests/Pest.php` was never seen by Patchwork's preprocessor.
The practical rule: **never hand-define a function in `tests/Pest.php` that tests mock with Brain Monkey.** Two failure modes exist, and the rule avoids both:

- A hand-defined *hook* function wins the `function_exists` race silently: Brain Monkey's guarded definitions are skipped and its hook storage stays empty, so `expectAdded()`/`has()` assertions pass against nothing.
- Mocking an earlier hand-defined *non-hook* function with `Functions\expect()` throws Patchwork's `DefinedTooEarly` error — loud, but confusing if you do not know the cause.

## Lifecycle Wiring

`tests/Pest.php` wires both frameworks suite-wide:

```php
pest()->beforeEach(function () {
    wp_test_reset();
    Monkey\setUp();
});

pest()->afterEach(function () {
    Monkey\tearDown();
});
```

- `wp_test_reset()` clears recorded state and resets the `Plugin` singleton.
- `Monkey\setUp()` loads Brain Monkey's WordPress function definitions.
- `Monkey\tearDown()` resets Brain Monkey's container, closes Mockery (unmet expectations fail the test), and restores Patchwork redefinitions.

Note that `pest()->` is required: a bare `beforeEach()` call in `Pest.php` is silently ignored by Pest v4.

## The State Registry

`wp_test_state()` returns the global state array by reference, so tests can both inspect it and seed it:

```php
// Simulate a compiled .mo file so load_plugin_textdomain() returns true
wp_test_state()['mo_files']['wp-plugin-radier'] = true;

// Simulate the WordPress main loop for filterContent()
wp_test_state()['query_context'] = ['is_main_query' => true, 'in_the_loop' => true];

// Inspect which textdomains were requested
expect(wp_test_state()['textdomains'])->toHaveKey('wp-plugin-radier');
```

## Brain Monkey in Tests

Brain Monkey brings Mockery-backed expectations for the WordPress plugin API.

### Expect hooks to be added

```php
use Brain\Monkey;

it('registers hooks on boot', function () {
    Plugin::init(PluginContext::fromFile(__DIR__ . '/../../wp-plugin-radier.php'));

    expect(Monkey\Actions\has('init'))->toBeTrue()
        ->and(Monkey\Filters\has('the_content'))->toBeTrue();
});
```

`Monkey\Actions\has()` and `Monkey\Filters\has()` mirror `has_action()`/`has_filter()`: with a callback they return the registered priority (int) or `false`.

### Expect exact calls and capture callbacks

```php
use Brain\Monkey\Functions;
use Mockery;

it('registers the example post type', function () {
    Functions\expect('register_post_type')->once()->with(
        'example',
        Mockery::on(fn (array $args): bool => $args['labels']['name'] === 'Examples'),
    );

    $service = exampleService();
    $service->registerPostType();
});
```

Expectations are validated by `Monkey\tearDown()`; an unmet expectation fails the test even if no Pest assertion references it.

To capture a registered callback (for example the `plugins_loaded` closure from the entrypoint), install the expectation before the code under test runs and store the argument inside a `Mockery::on()` matcher.

### Brain Monkey's do_action does not execute callbacks

Brain Monkey records and verifies hook API calls; its `do_action()` fulfils expectations and returns, without invoking registered callbacks, and its `apply_filters()` passes the value through.
Test wiring with `expectAdded()`/`has()`, and test the wired behavior by calling the service method directly.
See `PluginTest` for the pattern.

## Writing Unit Tests

Unit tests live in `tests/Unit/` under the `WpPluginRadier\Tests\Unit` namespace.
Because services take their dependencies explicitly, you can test them directly with standard Pest expectations.

### Testing a Service

```php
<?php

declare(strict_types=1);

namespace WpPluginRadier\Tests\Unit;

use WpPluginRadier\PluginContext;
use WpPluginRadier\Services\ExampleService;

it('appends signature in the main loop', function () {
    wp_test_state()['query_context'] = ['is_main_query' => true, 'in_the_loop' => true];

    $service = new ExampleService(PluginContext::fromFile('/wp-content/plugins/radier/radier.php'));

    $output = $service->filterContent('Hello World');

    expect($output)->toBe("Hello World\n<!-- Processed by WpPluginRadier -->");
});
```

### Testing the Entrypoint

`tests/Unit/EntrypointTest.php` loads the real `wp-plugin-radier.php` and verifies the version header parsing, the lifecycle hook registration, and the full `plugins_loaded` → `Plugin::init()` bootstrap.
The `loadEntrypoint()` helper in that file `require_once`s the entrypoint once per process; tests never register entrypoint hooks at file load time, because `wp_test_reset()` would then leak them into whichever test runs next.
When you rename the plugin, update `entrypointFile()` in that test to point at the new filename (see the rename checklist in `docs/scaffolding-new-plugin.md`).

The missing-autoloader guard cannot execute in-process (`vendor/` exists in a dev checkout), so it stays covered by code review. The `Requires PHP:` header needs no guard: WordPress refuses activation and skips loading on older hosts.
Everything after the autoloader guard is exercised by `EntrypointTest`.

## Architecture Testing

Architecture tests live in `tests/ArchTest.php`.
They use Pest's built-in architecture plugin to enforce codebase conventions automatically.

```php
<?php

declare(strict_types=1);

arch('source files declare strict types')
    ->expect('WpPluginRadier')
    ->toUseStrictTypes();

arch('source files do not use debugging functions')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray'])
    ->not->toBeUsed();
```

When you change the plugin namespace in `composer.json`, update the namespace string passed to `->expect('MyVendor\\MyPlugin')` in `tests/ArchTest.php`.

## Adding New WordPress Stubs

If a new service calls a WordPress function that neither Brain Monkey nor `tests/Pest.php` provides:

1. Decide the owner: mock it with Brain Monkey (`Functions\when()` / `Functions\expect()`) if call expectations matter; hand-stub it in `tests/Pest.php` if tests need to seed or record state (like `mo_files`).
2. Hand stubs go in `tests/Pest.php`, wrapped in `if (!function_exists(...))`, recording arguments in `wp_test_state()` and modeling the real failure mode.
3. Add new state keys to the shape docblock and to `wp_test_reset()`.
4. Remember the ownership rule: a function hand-defined in `Pest.php` was never preprocessed by Patchwork (which boots inside `Monkey\setUp()`), so Brain Monkey cannot mock it.

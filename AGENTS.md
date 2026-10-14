# AGENTS.md

## Project Overview

WordPress plugin 'radier' scaffold (radier: the plain concrete slab poured over the ground, a firm level base): PHP 8.3+, strict types, no runtime Composer dependencies. Domain services are isolated from the WordPress runtime and tested with Pest v4 against in-memory WP stubs (no WP install or database needed). Meant to be copied and renamed per plugin; see `docs/scaffolding-new-plugin.md`.

## Setup

- Requires PHP >= 8.3 and Composer.
- Install: `composer install`

## Commands

- Test (all): `composer test` (equivalent to `./vendor/bin/pest`)
- Coverage: `composer test:coverage`
- Single test file: `./vendor/bin/pest tests/Unit/ExampleServiceTest.php`
- Filter by name: `./vendor/bin/pest --filter="filters content"`
- Syntax check: `php -l <file>`
- After changing PSR-4 prefixes in `composer.json`: `composer dump-autoload`
- Static analysis (PHPStan, level max): `composer analyse`
- No lint or formatting tooling is configured. Do not invent such commands.

## Architecture

```
wp-plugin-radier.php    Entrypoint: vendor guard, activation/deactivation hooks, boots on plugins_loaded; `Requires PHP` header is the PHP minimum (WordPress enforces it)
phpstan.neon            PHPStan config: level max, analyses src + entrypoint files only
uninstall.php            Destructive cleanup; guarded by WP_UNINSTALL_PLUGIN
src/Plugin.php           Singleton coordinator; pure constructor; registers services + hooks in registerServices()
src/PluginContext.php    Final readonly value object: paths, URLs, textdomain (fromFile(), path(), url(), languagesPath())
src/Services/            Domain services (I18nService, ExampleService); no WP hook calls inside (arch-enforced)
tests/Pest.php           In-memory WP stubs (paths, mo_files, translations, query context) + wp_test_state()/wp_test_reset()
tests/ArchTest.php       Pest arch rules over the WpPluginRadier namespace
tests/Unit/              Isolated unit tests
docs/                    Developer docs: scaffolding, architecture, testing
```

Execution flow: entrypoint guards -> `add_action('plugins_loaded')` -> `Plugin::init(PluginContext::fromFile(__FILE__))` -> `boot()` -> `registerServices()` wires services to WP hooks (`init`, `the_content`).

Service access is by FQCN: `Plugin::init($context)->get(ExampleService::class)`. There is no string-keyed `getService()`.

## Testing

- Brain Monkey (with Mockery + Patchwork) owns the WP hook API plus `trailingslashit`/`untrailingslashit`, defined with `function_exists` guards at the first `Monkey\setUp()`. `register_post_type` is not predefined: it exists only after `Functions\expect()`/`Functions\when()` create it.
- `tests/Pest.php` owns state-driven stubs Brain Monkey lacks: paths, `load_plugin_textdomain` (mo_files-driven bool), lifecycle hook recording, `__` (translation recording), query context, `is_multisite`, `esc_html`.
- Ownership rule: never hand-define a function in `tests/Pest.php` that tests mock with Brain Monkey (`Functions\expect`, `Actions\expectAdded`). Patchwork boots inside `Monkey\setUp()`: mocking an earlier hand-defined function throws Patchwork's `DefinedTooEarly` error, and a hand-defined hook function silently wins BM's `function_exists` race, leaving BM's hook storage empty.
- Global `pest()->beforeEach()` runs `wp_test_reset()` + `Monkey\setUp()`; `pest()->afterEach()` runs `Monkey\tearDown()` (validates Mockery expectations). The bare `beforeEach()` form is silently ignored by Pest v4; always use the `pest()->` fluent form for global hooks.
- Brain Monkey's `do_action()` does not execute registered callbacks and `apply_filters()` passes values through: assert wiring with `Monkey\Actions\has()`/`expectAdded()`, assert behavior by calling service methods directly.
- Inspect state via `wp_test_state()['textdomains'|'mo_files'|'lifecycle_hooks'|'translations'|'query_context']`; seed it for failure-path tests; fire hooks with the Brain Monkey functions.
- Custom expectations: `expect(...)->toHaveRegisteredAction($hook)`, `toHaveRegisteredFilter($hook)` (Brain Monkey-backed).
- Arch rules enforced in `tests/ArchTest.php`: strict types everywhere; no `dd`/`dump`/`var_dump`/`print_r`/`ray`; classes in `WpPluginRadier\Services` final; `PluginContext` readonly; `add_action`/`add_filter` never used inside `WpPluginRadier\Services`.
- New WP function needed by a service? Prefer Brain Monkey mock APIs; hand-stub in `tests/Pest.php` only for state-driven semantics, wrapped in `if (!function_exists(...))`, recorded in `wp_test_state()`, modeling the real failure mode.
- `EntrypointTest` loads the real `wp-plugin-radier.php` once per process via `loadEntrypoint()`; never register entrypoint hooks at test-file scope, `wp_test_reset()` would leak them across tests. The autoloader guard stays untestable in-process. The `Requires PHP:` header needs no guard: WordPress refuses activation and skips loading on older hosts.

## Conventions & Gotchas

- `declare(strict_types=1)` in every file; modern PHP only (readonly, constructor promotion, `match`).
- Hook wiring lives only in `Plugin::registerServices()`. Services expose plain methods; the coordinator attaches them.
- WP filter callbacks: accept `mixed $value` at the boundary, validate, then delegate to a strictly typed internal method. Other plugins can return `null`/wrong types upstream and trigger an uncatchable `TypeError`. Guard context-sensitive filters (`is_main_query()`, `in_the_loop()`) so they never touch feeds, REST, widgets, or excerpts.
- `Plugin::init()` is a singleton. `Plugin::reset()` exists for tests; the global `beforeEach` already calls it.
- Textdomain loads on `init` only; WordPress 6.7+ raises `_doing_it_wrong` for earlier translation calls. The textdomain default lives only in the `PluginContext` constructor; `fromFile()` inherits it.
- Scaffold rename checklist (see `docs/scaffolding-new-plugin.md` §4 for the full mandatory list): composer.json name/authors + both PSR-4 prefixes, main plugin filename (+ `entrypointFile()` in EntrypointTest), plugin header fields, the `WP_PLUGIN_RADIER_VERSION` constant (mandatory: global constants collide across sibling plugins), notice strings, namespace across `src/`, `tests/`, and `uninstall.php`, all four `->expect('WpPluginRadier...')` strings in `tests/ArchTest.php`, the `PluginContext` textdomain default, `.pot`/`.po` filenames and headers, the `phpstan.neon` paths, and removal (or full rename) of `ExampleService` and its test. Done = `composer test` + `composer analyse` green and `rg -i radier` returns nothing.
- `WP_PLUGIN_RADIER_VERSION` is parsed from the plugin header with `get_file_data()` at load; the header `Version:` line is the single source of truth.
- `phpstan.neon` wires `php-stubs/wordpress-stubs` via `scanFiles`, so WP functions resolve without loading WordPress. Analysis covers `src/` plus the two entrypoint files, not tests.
- If you add third-party `require` packages, prefix vendor namespaces (Strauss or PHP-Scoper) or they will collide with other plugins.
- `._*` files are macOS copy artifacts; ignore them, never edit.
- The git repository is on `main`; there is no CI config, CONTRIBUTING file, or PR template yet, so no commit/PR conventions are enforced.

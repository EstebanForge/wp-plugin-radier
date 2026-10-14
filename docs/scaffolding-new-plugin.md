# Scaffolding a New Plugin

Follow these steps to turn this scaffold into your custom WordPress plugin.

## 1. Copy the Scaffold Directory

Copy the `wp-plugin-radier/` directory to your target destination or rename the directory.

```bash
cp -r wp-plugin-radier/ /path/to/my-custom-plugin/
cd /path/to/my-custom-plugin/
```

## 2. Update `composer.json`

Open `composer.json` and change the package name, description, and PSR-4 namespace mappings. Keep `require-dev`, `config.allow-plugins`, and `scripts` exactly as shown: dropping `phpstan/phpstan` or `php-stubs/wordpress-stubs` breaks `composer analyse`, and dropping `config.allow-plugins` or `scripts` silently disables the Pest plugin and the `composer test` / `composer analyse` entry points.

```json
{
  "name": "vendor-name/my-custom-plugin",
  "description": "Description of your custom plugin",
  "type": "wordpress-plugin",
  "license": "GPL-2.0-or-later",
  "require": {
    "php": "^8.3"
  },
  "require-dev": {
    "brain/monkey": "^2.7",
    "pestphp/pest": "^4.0",
    "php-stubs/wordpress-stubs": "^7.1",
    "phpstan/phpstan": "^2.3"
  },
  "autoload": {
    "psr-4": {
      "MyVendor\\MyPlugin\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "MyVendor\\MyPlugin\\Tests\\": "tests/"
    }
  },
  "config": {
    "allow-plugins": {
      "pestphp/pest-plugin": true
    },
    "sort-packages": true
  },
  "scripts": {
    "analyse": "phpstan --memory-limit=1G",
    "test": "pest",
    "test:coverage": "pest --coverage"
  }
}
```

Regenerate the Composer autoloader:

```bash
composer dump-autoload
```

> [!WARNING]
> **Vendor Dependency Isolation in Production**:
> If you add third-party Composer packages to `"require"` (e.g. Guzzle, Symfony components), their namespaces will collide with other WordPress plugins. Use a prefixer like [Strauss](https://github.com/BrianHenryIE/strauss) or [PHP-Scoper](https://github.com/humbug/php-scoper) in your build pipeline to prefix all vendor dependencies into `MyVendor\MyPlugin\Vendor\*`.

## 3. Rename and Update the Main Plugin File

Rename `wp-plugin-radier.php` to match your plugin slug, for example `my-custom-plugin.php`.

The file includes:
1. **Plugin Header**: `Plugin Name`, `Plugin URI`, `Description`, `Version`, `Text Domain`, and `Domain Path` are read by WordPress; `WP_PLUGIN_RADIER_VERSION` is parsed from the `Version` header at load time via `get_file_data()`, so the header stays the single source of truth.
2. **PHP Requirement**: the `Requires PHP: 8.3` header field. WordPress refuses activation and skips loading on an older host, so there is no in-file guard. Code that needs the value reads it with the same `get_file_data()` call shape as the version constant.
3. **Missing Autoloader Notice**: Warns admins if `vendor/autoload.php` is missing instead of white-screening.
4. **Activation & Deactivation Lifecycle**: `register_activation_hook` and `register_deactivation_hook` are registered at file scope.
5. **Bootstrapping**: Initialized on `plugins_loaded` via `Plugin::init(PluginContext::fromFile(__FILE__))`.

## 4. Rename Checklist

Work through every item before shipping a copy. Items 1-12 are all mandatory:

1. `composer.json`: package `name`, `description`, `authors`, and both PSR-4 prefixes (`autoload` and `autoload-dev`).
2. Main plugin filename (`wp-plugin-radier.php`), and the path returned by `entrypointFile()` in `tests/Unit/EntrypointTest.php`, which `require_once`s that file.
3. Plugin header fields in the main file: `Plugin Name`, `Plugin URI`, `Description`, `Author`, `Version`, `Text Domain`, `Domain Path`.
4. The `WP_PLUGIN_RADIER_VERSION` constant name in the main file (`wp-plugin-radier.php`) and its references in `tests/Unit/EntrypointTest.php`. This rename is mandatory, not cosmetic: the constant is global, so two plugins built from this template that both define `WP_PLUGIN_RADIER_VERSION` collide with a PHP warning on activation and report the wrong version.
5. Admin notice strings containing `WP Plugin Radier` in the main file's autoloader guard.
6. `namespace WpPluginRadier` across all `src/` files, `uninstall.php`, and the `WpPluginRadier\Tests\Unit` namespace in `tests/`.
7. `use WpPluginRadier\...` import statements in `tests/` and in `docs/` examples.
8. All four namespace strings in `tests/ArchTest.php`: `'WpPluginRadier'` (strict types rule), `'WpPluginRadier\Services'` (final-classes rule), `'WpPluginRadier\PluginContext'` (readonly rule), and `'WpPluginRadier\Services'` again (hook-isolation rule). Pest only checks classes matching these strings; a partially renamed rule silently matches nothing and still passes.
9. Textdomain default in the `PluginContext` constructor (the only place it is defined; `fromFile()` inherits it), plus the textdomain strings asserted in `tests/Unit/PluginContextTest.php` and `tests/Unit/ExampleServiceTest.php`.
10. Translation files: rename `languages/wp-plugin-radier.pot` and any `languages/wp-plugin-radier-{locale}.po` files, and update their header metadata (`Project-Id-Version`, `X-Domain`, language-team lines).
11. `phpstan.neon` `paths`: it lists `src`, the main plugin file, and `uninstall.php` by name.
12. The example service: remove `src/Services/ExampleService.php` and `tests/Unit/ExampleServiceTest.php` before shipping (or rename both and re-slug the `POST_TYPE` constant and the processed-signature string). A copy that keeps it registers a public, unprefixed `example` post type with an archive and appends a signature comment to every post.
13. Documentation and metadata: `README.md`, `AGENTS.md`, `docs/*.md`, and any remaining template mentions anywhere in the copy.
14. Final gate: `composer test` and `composer analyse` both pass, and `rg -i radier` returns nothing. If the search still finds matches, the rename is not done.

## 5. Add Custom Services & Defend Boundaries

Add your domain classes inside `src/Services/`.
Register your services in `Plugin::registerServices()` using FQCN registration:

```php
private function registerServices(): void
{
    $customService = new MyCustomService($this->context);
    $this->services[MyCustomService::class] = $customService;

    add_action('init', [$customService, 'onInit']);
    add_filter('the_title', [$customService, 'filterTitle']);
}
```

> [!TIP]
> **WordPress Filter Boundary Defense**:
> When implementing WordPress filter callbacks in PHP with `declare(strict_types=1)`, accept `mixed $value` at the boundary method. Upstream filters in other plugins may return `null` or invalid types, which triggers an uncatchable `TypeError` if typed strictly. Validate at the boundary, then delegate to strictly typed internal methods.
> Guard context-sensitive filters too: `ExampleService::filterContent()` checks `is_main_query()` and `in_the_loop()` so it only touches front-end main content, never feeds, REST responses, widgets, or excerpts.

## 6. Verify with Tests

Run the test suite to ensure your changes pass all assertions and architecture rules:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

## 7. Release Build

The scaffold's own tests and development tooling live in `require-dev`.
A production install must never ship them: a plain `composer install` puts Pest, Mockery, and PHPStan on the Composer files autoload, so their global functions load on every WordPress request.

Build releases with:

```bash
composer install --no-dev -o
```

The missing-autoloader admin notice in the main plugin file already names this command, so an admin who activates a copy without `vendor/` sees the right instruction.

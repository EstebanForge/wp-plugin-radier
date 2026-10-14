# WP Plugin Radier

A radier is the plain concrete slab poured over the ground: a firm, level base for a house, terrace, extension, or shed. This repository is that slab for WordPress plugins: PHP 8.3+, strict types, business logic isolated from the WordPress runtime, KISS architecture, and a Pest v4 test harness with in-memory WordPress stubs. Pour it once, then build your plugin on top.

## Documentation Index

- [Scaffolding a New Plugin](docs/scaffolding-new-plugin.md): Step-by-step procedure to configure a new plugin from this radier, including the rename checklist and release builds.
- [Architecture and Lifecycle](docs/architecture-and-lifecycle.md): Explanation of `PluginContext`, `Plugin`, and domain service design.
- [Testing with Pest v4](docs/testing-with-pest.md): How to write isolated unit tests and architecture rules with Pest v4 and Brain Monkey.
- [AGENTS.md](AGENTS.md): Working conventions for AI coding agents on this repository.

## Directory Structure

```
wp-plugin-radier/
├── README.md                   # Plugin scaffold overview and documentation index
├── AGENTS.md                   # Conventions for AI coding agents working on this repo
├── composer.json               # Package definition, PSR-4 autoloading, and test scripts
├── phpunit.xml                 # Pest / PHPUnit test suite configuration
├── phpstan.neon                # PHPStan config: level max, WP function stubs via scanFiles
├── wp-plugin-radier.php        # Plugin entrypoint, guards, and lifecycle hooks
├── uninstall.php               # Clean uninstall handler
├── languages/                  # Translations: .pot template plus per-locale .po files
├── docs/                       # Developer documentation
│   ├── scaffolding-new-plugin.md
│   ├── architecture-and-lifecycle.md
│   └── testing-with-pest.md
├── src/                        # Plugin source code
│   ├── Plugin.php              # Main plugin lifecycle coordinator & service registry
│   ├── PluginContext.php       # Value object for paths, URLs, and textdomain
│   └── Services/               # Domain services and business logic
│       ├── ExampleService.php  # Sample domain service
│       └── I18nService.php     # Textdomain loader service
└── tests/                      # Test suite
    ├── ArchTest.php            # Pest architecture tests
    ├── Pest.php                # Test bootstrap, WordPress runtime stubs, Brain Monkey wiring
    └── Unit/                   # Isolated unit tests
        ├── EntrypointTest.php  # Loads the real entrypoint: version, lifecycle, bootstrap
        ├── ExampleServiceTest.php
        ├── I18nServiceTest.php
        ├── PluginContextTest.php
        └── PluginTest.php
```

## Quick Start

1. Install dependencies:
```bash
composer install
```

2. Run the test suite:
```bash
composer test
```

3. Run static analysis (PHPStan, level max):
```bash
composer analyse
```

Shipping a production build? Use `composer install --no-dev -o`; see [Scaffolding a New Plugin §7](docs/scaffolding-new-plugin.md#7-release-build).

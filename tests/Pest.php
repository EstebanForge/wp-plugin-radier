<?php

declare(strict_types=1);

use Brain\Monkey;
use WpPluginRadier\Plugin;

/*
|--------------------------------------------------------------------------
| Test Harness
|--------------------------------------------------------------------------
|
| Ownership split with Brain Monkey:
|
| - Brain Monkey owns the WordPress hook API (add_action, add_filter,
|   do_action, apply_filters, did_action, has_action, ...), trailingslashit
|   and register_post_type. It defines them at the first Monkey\setUp(),
|   and Patchwork can only redefine functions defined after it boots, so
|   never hand-define a function here that tests mock with Functions\expect()
|   or Actions\expectAdded() — Brain Monkey's guarded definitions would be
|   skipped and its storage would silently stay empty.
|
| - This file owns the state-driven stubs Brain Monkey does not provide:
|   paths, load_plugin_textdomain (mo_files-driven bool), lifecycle hook
|   recording, __ (translation recording), query context and multisite.
|
*/

if (!function_exists('wp_test_state')) {
    /**
     * @return array{
     *     textdomains: array<string, array{deprecated: mixed, plugin_rel_path: string|false}>,
     *     mo_files: array<string, bool>,
     *     lifecycle_hooks: array{activation?: list<array{file: string, callback: callable}>, deactivation?: list<array{file: string, callback: callable}>},
     *     translations: list<array{text: string, domain: string}>,
     *     query_context: array{is_main_query?: bool, in_the_loop?: bool}
     * }
     */
    function &wp_test_state(): array
    {
        if (!isset($GLOBALS['wp_test_state'])) {
            $GLOBALS['wp_test_state'] = [
                'textdomains' => [],
                'mo_files' => [],
                'lifecycle_hooks' => [],
                'translations' => [],
                'query_context' => [],
            ];
        }

        return $GLOBALS['wp_test_state'];
    }
}

if (!function_exists('wp_test_reset')) {
    function wp_test_reset(): void
    {
        $GLOBALS['wp_test_state'] = [
            'textdomains' => [],
            'mo_files' => [],
            'lifecycle_hooks' => [],
            'translations' => [],
            'query_context' => [],
        ];

        Plugin::reset();
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string
    {
        return trailingslashit(dirname($file));
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $parts = explode('/', $file);
        $count = count($parts);

        return $count >= 2 ? $parts[$count - 2] . '/' . $parts[$count - 1] : basename($file);
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url(string $path = '', string $plugin = ''): string
    {
        $base = 'https://example.com/wp-content/plugins/' . basename(dirname($plugin));

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('load_plugin_textdomain')) {
    /**
     * Real WP returns false when no .mo file resolves. Tests simulate that
     * with wp_test_state()['mo_files'][$domain] = true; default is false.
     */
    function load_plugin_textdomain(string $domain, mixed $deprecated = false, string|false $plugin_rel_path = false): bool
    {
        $state = &wp_test_state();
        $state['textdomains'][$domain] = [
            'deprecated' => $deprecated,
            'plugin_rel_path' => $plugin_rel_path,
        ];

        return (bool) ($state['mo_files'][$domain] ?? false);
    }
}

if (!function_exists('get_file_data')) {
    function get_file_data(string $file, array $default_headers = [], bool $markup = false): array
    {
        $contents = is_readable($file) ? (string) file_get_contents($file) : '';

        $headers = [];
        foreach ($default_headers as $field => $header_key) {
            preg_match(
                '/^(?:[ \t]*<\?php)?[ \t\/*#@]*' . preg_quote((string) $header_key, '/') . ':(.*)$/mi',
                $contents,
                $matches
            );
            $headers[$field] = isset($matches[1]) ? trim($matches[1]) : '';
        }

        return $headers;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        $state = &wp_test_state();
        $state['translations'][] = [
            'text' => $text,
            'domain' => $domain,
        ];

        return $text;
    }
}

if (!function_exists('is_main_query')) {
    function is_main_query(): bool
    {
        return wp_test_state()['query_context']['is_main_query'] ?? false;
    }
}

if (!function_exists('in_the_loop')) {
    function in_the_loop(): bool
    {
        return wp_test_state()['query_context']['in_the_loop'] ?? false;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, callable $callback): void
    {
        $state = &wp_test_state();
        $state['lifecycle_hooks']['activation'][] = [
            'file' => $file,
            'callback' => $callback,
        ];
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, callable $callback): void
    {
        $state = &wp_test_state();
        $state['lifecycle_hooks']['deactivation'][] = [
            'file' => $file,
            'callback' => $callback,
        ];
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool
    {
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Pest Lifecycle Hooks & Custom Expectations
|--------------------------------------------------------------------------
*/

pest()->beforeEach(function () {
    wp_test_reset();
    Monkey\setUp();
});

pest()->afterEach(function () {
    Monkey\tearDown();
});

expect()->extend('toHaveRegisteredAction', function (string $hook) {
    expect(Monkey\Actions\has($hook))->toBeTrue();

    return $this;
});

expect()->extend('toHaveRegisteredFilter', function (string $hook) {
    expect(Monkey\Filters\has($hook))->toBeTrue();

    return $this;
});

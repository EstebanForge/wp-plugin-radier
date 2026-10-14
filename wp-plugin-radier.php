<?php
/**
 * Plugin Name: WP Plugin Radier
 * Plugin URI: https://example.com/wp-plugin-radier
 * Description: WordPress plugin base layer with Pest v4 testing architecture.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.3
 * Author: Esteban
 * License: GPL-2.0-or-later
 * Text Domain: wp-plugin-radier
 * Domain Path: /languages
 */

declare(strict_types=1);

namespace WpPluginRadier;

defined('ABSPATH') || exit;

define('WP_PLUGIN_RADIER_VERSION', get_file_data(__FILE__, ['Version' => 'Version'])['Version']);

// 1. Composer autoloader presence guard. The `Requires PHP:` header is the PHP minimum;
// WordPress refuses activation and skips loading on older hosts, so no runtime guard exists.
$autoloader = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>' .
            esc_html('WP Plugin Radier dependencies are missing. Run `composer install --no-dev -o` inside the plugin directory.') .
            '</p></div>';
    });
    return;
}

require_once $autoloader;

// 2. Lifecycle hooks must be registered at file scope (before plugins_loaded).
register_activation_hook(__FILE__, static function (bool $networkWide = false): void {
    // Perform activation logic (e.g. initial options, database migrations, rewrite rules).
    if ($networkWide && is_multisite()) {
        // Multisite network activation handling if needed.
        return;
    }
});

register_deactivation_hook(__FILE__, function (): void {
    // Perform cleanup (e.g. clear scheduled crons, flush rewrites).
});

// 3. Main runtime bootstrap.
add_action('plugins_loaded', function (): void {
    Plugin::init(PluginContext::fromFile(__FILE__));
});

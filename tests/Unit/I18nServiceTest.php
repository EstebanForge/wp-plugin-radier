<?php

declare(strict_types=1);

namespace WpPluginRadier\Tests\Unit;

use WpPluginRadier\PluginContext;
use WpPluginRadier\Services\I18nService;

function i18nService(): I18nService
{
    return new I18nService(new PluginContext(
        mainFile: '/wp-content/plugins/custom-plugin/custom-plugin.php',
        path: '/wp-content/plugins/custom-plugin/',
        url: 'https://example.com/wp-content/plugins/custom-plugin/',
        textDomain: 'custom-plugin',
    ));
}

it('loads plugin textdomain when a compiled mo file is present', function () {
    wp_test_state()['mo_files']['custom-plugin'] = true;

    $i18n = i18nService();

    expect($i18n->isLoaded())->toBeFalse();

    $result = $i18n->loadTextdomain();

    expect($result)->toBeTrue()
        ->and($i18n->isLoaded())->toBeTrue()
        ->and(wp_test_state()['textdomains'])->toHaveKey('custom-plugin')
        ->and(wp_test_state()['textdomains']['custom-plugin']['plugin_rel_path'])->toBe('custom-plugin/languages');
});

it('reports failure when no mo file resolves', function () {
    $i18n = i18nService();

    $result = $i18n->loadTextdomain();

    expect($result)->toBeFalse()
        ->and($i18n->isLoaded())->toBeFalse()
        ->and(wp_test_state()['textdomains'])->toHaveKey('custom-plugin');
});

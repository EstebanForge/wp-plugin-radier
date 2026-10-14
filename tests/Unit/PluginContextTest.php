<?php

declare(strict_types=1);

use WpPluginRadier\PluginContext;

it('constructs with explicit properties', function () {
    $context = new PluginContext(
        mainFile: '/var/www/html/wp-content/plugins/my-plugin/my-plugin.php',
        path: '/var/www/html/wp-content/plugins/my-plugin/',
        url: 'https://example.com/wp-content/plugins/my-plugin/',
        textDomain: 'my-plugin',
    );

    expect($context->mainFile)->toBe('/var/www/html/wp-content/plugins/my-plugin/my-plugin.php')
        ->and($context->path)->toBe('/var/www/html/wp-content/plugins/my-plugin/')
        ->and($context->url)->toBe('https://example.com/wp-content/plugins/my-plugin/')
        ->and($context->textDomain)->toBe('my-plugin')
        ->and($context->path('src/Core.php'))->toBe('/var/www/html/wp-content/plugins/my-plugin/src/Core.php')
        ->and($context->url('assets/main.css'))->toBe('https://example.com/wp-content/plugins/my-plugin/assets/main.css')
        ->and($context->languagesPath())->toBe('/var/www/html/wp-content/plugins/my-plugin/languages');
});

it('resolves context from main plugin file', function () {
    $file = '/var/www/html/wp-content/plugins/sample-plugin/sample-plugin.php';
    $context = PluginContext::fromFile($file);

    expect($context->path)->toBe('/var/www/html/wp-content/plugins/sample-plugin/')
        ->and($context->url)->toBe('https://example.com/wp-content/plugins/sample-plugin/')
        ->and($context->textDomain)->toBe('wp-plugin-radier');
});

it('allows custom base url override', function () {
    $file = '/var/www/html/wp-content/plugins/sample-plugin/sample-plugin.php';
    $context = PluginContext::fromFile($file, 'https://cdn.example.com/sample-plugin');

    expect($context->url)->toBe('https://cdn.example.com/sample-plugin/')
        ->and($context->url('dist/bundle.js'))->toBe('https://cdn.example.com/sample-plugin/dist/bundle.js');
});

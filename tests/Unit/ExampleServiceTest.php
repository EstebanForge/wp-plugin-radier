<?php

declare(strict_types=1);

namespace WpPluginRadier\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use WpPluginRadier\PluginContext;
use WpPluginRadier\Services\ExampleService;

function exampleService(): ExampleService
{
    return new ExampleService(PluginContext::fromFile('/wp-content/plugins/radier/radier.php'));
}

it('registers the example post type with labels in the plugin textdomain', function () {
    $service = exampleService();

    expect($service->isPostTypeRegistered())->toBeFalse();

    Functions\expect('register_post_type')->once()->with(
        'example',
        Mockery::on(function (array $args): bool {
            return $args['labels']['name'] === 'Examples'
                && $args['labels']['singular_name'] === 'Example';
        })
    );

    $service->registerPostType();

    expect($service->isPostTypeRegistered())->toBeTrue()
        ->and(wp_test_state()['translations'])->toBe([
            ['text' => 'Examples', 'domain' => 'wp-plugin-radier'],
            ['text' => 'Example', 'domain' => 'wp-plugin-radier'],
        ]);
});

it('appends signature to non-empty content while in the main loop', function () {
    wp_test_state()['query_context'] = ['is_main_query' => true, 'in_the_loop' => true];

    $service = exampleService();

    $output = $service->filterContent('<p>Hello World</p>');

    expect($output)->toBe("<p>Hello World</p>\n<!-- Processed by WpPluginRadier -->");

    // Idempotence: registering the same content twice must not duplicate the signature
    expect($service->filterContent($output))->toBe($output);
});

it('returns content untouched outside the main loop', function () {
    wp_test_state()['query_context'] = ['is_main_query' => false, 'in_the_loop' => false];

    $service = exampleService();

    expect($service->filterContent('<p>Hello World</p>'))->toBe('<p>Hello World</p>');
});

it('handles null and invalid types gracefully without throwing TypeError', function () {
    wp_test_state()['query_context'] = ['is_main_query' => true, 'in_the_loop' => true];

    $service = exampleService();

    // Defend against sloppy upstream filters returning null or non-strings
    expect($service->filterContent(null))->toBe('')
        ->and($service->filterContent(123))->toBe('')
        ->and($service->filterContent(['array']))->toBe('');
});

it('returns empty string when filtering empty content', function () {
    wp_test_state()['query_context'] = ['is_main_query' => true, 'in_the_loop' => true];

    $service = exampleService();

    expect($service->filterContent(''))->toBe('');
});

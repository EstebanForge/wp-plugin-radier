<?php

declare(strict_types=1);

namespace WpPluginRadier\Services;

use WpPluginRadier\PluginContext;

final class ExampleService
{
    private const string POST_TYPE = 'example';

    private const string PROCESSED_SIGNATURE = '<!-- Processed by WpPluginRadier -->';

    private bool $postTypeRegistered = false;

    public function __construct(
        private readonly PluginContext $context,
    ) {}

    public function registerPostType(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Examples', $this->context->textDomain),
                'singular_name' => __('Example', $this->context->textDomain),
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
        ]);

        $this->postTypeRegistered = true;
    }

    public function isPostTypeRegistered(): bool
    {
        return $this->postTypeRegistered;
    }

    /**
     * WordPress filter boundary: accepts mixed to defend against upstream filters returning null/invalid types.
     */
    public function filterContent(mixed $content): string
    {
        if (!is_string($content) || $content === '') {
            return is_string($content) ? $content : '';
        }

        // Feeds, REST responses, widgets and excerpts also fire the_content;
        // the signature belongs to front-end main-loop content only.
        if (!is_main_query() || !in_the_loop()) {
            return $content;
        }

        return $this->appendSignature($content);
    }

    /**
     * Internal strictly-typed domain logic.
     */
    public function appendSignature(string $content): string
    {
        if (str_contains($content, self::PROCESSED_SIGNATURE)) {
            return $content;
        }

        return "{$content}\n" . self::PROCESSED_SIGNATURE;
    }
}

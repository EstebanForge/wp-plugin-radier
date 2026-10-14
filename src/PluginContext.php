<?php

declare(strict_types=1);

namespace WpPluginRadier;

final readonly class PluginContext
{
    public function __construct(
        public string $mainFile,
        public string $path,
        public string $url,
        public string $textDomain = 'wp-plugin-radier',
    ) {}

    public static function fromFile(string $mainFile, ?string $customUrl = null): self
    {
        $path = plugin_dir_path($mainFile);
        $url = $customUrl ?? plugins_url('/', $mainFile);

        return new self(
            mainFile: $mainFile,
            path: str_ends_with($path, '/') ? $path : $path . '/',
            url: str_ends_with($url, '/') ? $url : $url . '/',
        );
    }

    public function path(string $relativePath = ''): string
    {
        $trimmed = ltrim($relativePath, '/\\');

        return $trimmed === '' ? $this->path : "{$this->path}{$trimmed}";
    }

    public function url(string $relativePath = ''): string
    {
        $trimmed = ltrim($relativePath, '/');

        return $trimmed === '' ? $this->url : "{$this->url}{$trimmed}";
    }

    public function languagesPath(): string
    {
        return $this->path('languages');
    }
}

<?php
namespace Wawoo\Plugin\Seo;

/**
 * Boots the SEO plugin. Adds a JSON-LD structured-data block on post pages
 * and refines the <meta description> to use the post excerpt when available.
 */
class Plugin
{
    public function boot(): void
    {
        // No-op; behaviour is registered via hooks declared in plugin.json.
    }
}

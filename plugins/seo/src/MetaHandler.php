<?php
namespace Wawoo\Plugin\Seo;

/**
 * Refines the meta block before <head> is rendered:
 * - trims title to ~60 chars
 * - trims description to ~160 chars
 */
class MetaHandler
{
    public function handle(?array &$meta): void
    {
        if (!is_array($meta)) {
            return;
        }
        if (isset($meta['title']) && mb_strlen($meta['title']) > 60) {
            $meta['title'] = mb_substr($meta['title'], 0, 57) . '...';
        }
        if (isset($meta['description']) && mb_strlen($meta['description']) > 160) {
            $meta['description'] = mb_substr($meta['description'], 0, 157) . '...';
        }
    }
}

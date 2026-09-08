<?php
/**
 * URL helpers shared by themes and plugins.
 */

namespace Wawoo\Core;

final class Url
{
    /**
     * Return an absolute URL for the given site-root-relative or already
     * absolute path, using BASE_URL as the origin. data: URIs and
     * protocol-relative (//host) URLs are returned unchanged.
     */
    public static function absolute(string $url): string
    {
        if ($url === '') {
            return $url;
        }
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $url)) {
            return $url;
        }
        if (!defined('BASE_URL')) {
            return $url;
        }
        $base = BASE_URL;
        return $base . ltrim($url, '/');
    }
}

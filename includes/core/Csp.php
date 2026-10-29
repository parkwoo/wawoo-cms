<?php
declare(strict_types=1);

namespace Wawoo\Core;

/**
 * Per-request Content-Security-Policy.
 *
 * script-src uses a nonce plus 'strict-dynamic': every inline <script> (and
 * same-origin <script src>) must carry the nonce, while scripts that a
 * nonce'd script creates at runtime (e.g. CDN loaders such as mermaid or
 * highlight.js) are trusted automatically. Inline event handlers (onclick=&)
 * are intentionally not supported — bind via addEventListener inside a
 * nonce'd script instead.
 *
 * Emit `attr()` on every <script> tag and the header() value is sent by
 * Bootstrap::init().
 */
final class Csp
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        if (self::$nonce === null) {
            self::$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        }
        return self::$nonce;
    }

    /** ` nonce="…"` ready to drop inside a <script …> tag. */
    public static function attr(): string
    {
        return ' nonce="' . htmlspecialchars(self::nonce(), ENT_QUOTES, 'UTF-8') . '"';
    }

    public static function header(): string
    {
        return "default-src 'self'; "
            . "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; "
            . "img-src 'self' data:; font-src 'self' data:; "
            . "style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'nonce-" . self::nonce() . "' 'strict-dynamic'";
    }
}

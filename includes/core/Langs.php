<?php
declare(strict_types=1);

namespace Wawoo\Core;

/**
 * Shared language whitelist for the core router, entry scripts and plugins.
 *
 * Configure with `SUPPORTED_LANGS` / `DEFAULT_LANGUAGE` in config.local.php.
 * The default keeps the historical `ja`/`zh` URL prefixes working out of the
 * box: the default language stays prefix-less, every other supported language
 * carries a `/<lang>/` prefix.
 */
final class Langs
{
    public const DEFAULT_SUPPORTED = ['ja', 'zh'];

    /** Prefixed (non-default) languages the router accepts. @return string[] */
    public static function supported(): array
    {
        $list = defined('SUPPORTED_LANGS') && is_array(SUPPORTED_LANGS)
            ? array_values(array_filter(array_map('strval', SUPPORTED_LANGS)))
            : self::DEFAULT_SUPPORTED;
        $default = self::default();
        return array_values(array_filter(
            $list,
            static fn(string $c): bool => $c !== '' && $c !== $default
        ));
    }

    public static function default(): string
    {
        return defined('DEFAULT_LANGUAGE') && is_string(DEFAULT_LANGUAGE) && DEFAULT_LANGUAGE !== ''
            ? DEFAULT_LANGUAGE
            : 'en';
    }

    public static function isSupported(string $code): bool
    {
        return in_array($code, self::supported(), true);
    }

    /** Language of the current request (?mlang when supported, else default). */
    public static function current(): string
    {
        $lang = (string)($_GET['mlang'] ?? '');
        return self::isSupported($lang) ? $lang : self::default();
    }

    /** URL-prefix alternation (longest code first), ready for a regex group. */
    public static function alternation(): string
    {
        $list = self::supported();
        usort($list, static fn($a, $b) => strlen($b) <=> strlen($a));
        return implode('|', array_map('preg_quote', $list));
    }
}

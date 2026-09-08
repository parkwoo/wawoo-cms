<?php
/**
 * PSR-4 style autoloader for wawoo-cms.
 *
 * Maps a namespace prefix (declared by manifest autoload.psr4) to a directory.
 * Plugins/themes register their own prefixes at boot time.
 */

namespace Wawoo\Core;

final class Autoloader
{
    private static array $prefixes = [];

    public static function add(string $prefix, string $baseDir): void
    {
        $prefix = rtrim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        self::$prefixes[$prefix] = $baseDir;
    }

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): bool
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
                return true;
            }
        }
        return false;
    }
}

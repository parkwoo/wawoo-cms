<?php
/**
 * wawoo-cms core: bootstrap, autoloader, manifest loader, hook registry.
 *
 * Boots themes & plugins discovered from disk, validates their manifests,
 * resolves dependencies, and registers them in load order.
 */

namespace Wawoo\Core;

final class Hook
{
    private static array $listeners = [];

    public static function on(string $name, callable|array $cb, int $priority = 10): void
    {
        self::$listeners[$name][$priority][] = $cb;
    }

    public static function fire(string $name, &$payload = null, ...$args): void
    {
        if (!isset(self::$listeners[$name])) {
            return;
        }
        ksort(self::$listeners[$name]);
        foreach (self::$listeners[$name] as $cbs) {
            foreach ($cbs as $cb) {
                if (is_callable($cb)) {
                    $cb($payload, ...$args);
                }
            }
        }
    }

    public static function filter(string $name, $value, ...$args)
    {
        if (!isset(self::$listeners[$name])) {
            return $value;
        }
        ksort(self::$listeners[$name]);
        foreach (self::$listeners[$name] as $cbs) {
            foreach ($cbs as $cb) {
                if (is_callable($cb)) {
                    $value = $cb($value, ...$args);
                }
            }
        }
        return $value;
    }

    public static function reset(): void
    {
        self::$listeners = [];
    }
}

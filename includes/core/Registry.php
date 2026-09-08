<?php
/**
 * Registry: discovers, validates, resolves dependencies, and boots
 * themes & plugins in deterministic order.
 */

namespace Wawoo\Core;

final class Registry
{
    /** @var array<string, Manifest> keyed by name */
    private array $items = [];
    /** @var array<string, bool> load order */
    private array $loaded = [];
    /** @var array<string, string> error messages */
    private array $errors = [];

    public function discover(string $type, string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $manifestFile = $dir . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . $type . '.json';
            $m = Manifest::load($manifestFile);
            if ($m === null || $m->type !== $type) {
                continue;
            }
            $key = $type . ':' . $m->name;
            if (isset($this->items[$key])) {
                $this->errors[$key] = "duplicate {$type} name: {$m->name}";
                continue;
            }
            $this->items[$key] = $m;
        }
    }

    /**
     * Resolve load order using a topological sort over requires.
     * Plugins depend on other plugins; themes depend on plugins.
     */
    public function resolve(array $enabledNames, string $type): array
    {
        $order = [];
        $visited = [];
        $stack = [];

        $visit = function (string $name) use (&$visit, &$order, &$visited, &$stack, $type) {
            $key = $type . ':' . $name;
            if (isset($visited[$key])) {
                return;
            }
            if (isset($stack[$key])) {
                $this->errors[$key] = "circular dependency at {$name}";
                return;
            }
            $stack[$key] = true;
            $m = $this->items[$key] ?? null;
            if ($m === null) {
                return;
            }
            foreach ($m->requires as $reqName => $constraint) {
                // "core" is a virtual always-present package (the blog kernel itself).
                if ($reqName === 'core') {
                    continue;
                }
                $reqKey = $type === 'theme' && strpos($reqName, 'plugin:') === 0
                    ? 'plugin:' . substr($reqName, 7)
                    : (strpos($reqName, ':') !== false ? $reqName : $type . ':' . $reqName);
                if (!isset($this->items[$reqKey])) {
                    $this->errors[$key] = "missing dependency: {$reqKey}";
                    continue;
                }
                $reqItem = $this->items[$reqKey];
                if (!Manifest::versionMatches($constraint, $reqItem->version)) {
                    $this->errors[$key] = "version mismatch: {$reqKey} needs {$constraint}, got {$reqItem->version}";
                    continue;
                }
                $visit($reqItem->name);
            }
            unset($stack[$key]);
            $visited[$key] = true;
            $order[] = $key;
        };

        foreach ($enabledNames as $name) {
            $visit($name);
        }
        return $order;
    }

    /**
     * Boot all enabled plugins/themes in resolved order.
     * - Registers PSR-4 autoload entries
     * - Includes declared hooks via Hook::on()
     * - Instantiates entry class and calls boot()
     */
    public function boot(array $enabledNames, string $type): void
    {
        $order = $this->resolve($enabledNames, $type);
        foreach ($order as $key) {
            $m = $this->items[$key];
            if (isset($this->loaded[$key])) {
                continue;
            }
            if ($m->namespace !== '') {
                Autoloader::add($m->namespace, $m->path . DIRECTORY_SEPARATOR . 'src');
            }
            foreach ($m->hooks as $hookName => $cbSpec) {
                foreach (is_array($cbSpec) ? $cbSpec : [$cbSpec] as $one) {
                    $cb = null;
                    if (is_string($one) && $m->namespace !== '' && class_exists($m->namespace . '\\' . $one)) {
                        $cls = $m->namespace . '\\' . $one;
                        $cb = [new $cls(), 'handle'];
                    } elseif (is_callable($one)) {
                        $cb = $one;
                    }
                    if ($cb !== null && is_callable($cb)) {
                        Hook::on($hookName, $cb);
                    }
                }
            }
            if ($m->entry !== '') {
                $cls = $m->namespace !== '' ? $m->namespace . '\\' . $m->entry : $m->entry;
                if (class_exists($cls)) {
                    (new $cls())->boot();
                }
            }
            $this->loaded[$key] = true;
        }
    }

    public function get(string $key): ?Manifest
    {
        return $this->items[$key] ?? null;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /** Find the active theme manifest by name, or the first enabled. */
    public function activeTheme(array $enabledNames): ?Manifest
    {
        foreach ($enabledNames as $name) {
            $m = $this->items['theme:' . $name] ?? null;
            if ($m !== null) {
                return $m;
            }
        }
        return null;
    }
}

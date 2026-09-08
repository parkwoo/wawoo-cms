<?php
/**
 * Manifest loader for themes and plugins.
 *
 * Each theme/plugin ships a {theme|plugin}.json manifest describing
 * name, version, dependencies, autoload mapping, and entry point class.
 * Manifests are validated and dependencies resolved in topological order.
 */

namespace Wawoo\Core;

final class Manifest
{
    public string $name;
    public string $type;
    public string $version;
    public string $path;
    public string $entry = '';
    public string $namespace = '';
    public array $requires = [];
    public array $hooks = [];
    public array $config = [];

    public static function load(string $path): ?self
    {
        if (!is_file($path)) {
            return null;
        }
        $raw = json_decode(file_get_contents($path), true);
        if (!is_array($raw)) {
            return null;
        }
        $m = new self();
        $m->name      = (string)($raw['name'] ?? '');
        $m->type      = (string)($raw['type'] ?? '');
        $m->version   = (string)($raw['version'] ?? '0.0.0');
        $m->path      = dirname($path);
        $m->entry     = (string)($raw['entry'] ?? '');
        $m->namespace = (string)($raw['namespace'] ?? '');
        $m->requires  = (array)($raw['requires'] ?? []);
        $m->hooks     = (array)($raw['hooks'] ?? []);
        $m->config    = (array)($raw['config'] ?? []);
        if ($m->name === '' || $m->type === '') {
            return null;
        }
        return $m;
    }

    /**
     * Version comparison helper.
     * Supports operators: >=, >, <=, <, =, !=, and bare version (means =).
     */
    public static function versionMatches(string $constraint, string $version): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }
        if (preg_match('/^(>=|<=|!=|>|<|=)\s*(.+)$/', $constraint, $m)) {
            $op = $m[1];
            $target = $m[2];
        } else {
            $op = '=';
            $target = $constraint;
        }
        $cmp = version_compare($version, $target);
        switch ($op) {
            case '>=': return $cmp >= 0;
            case '<=': return $cmp <= 0;
            case '>':  return $cmp >  0;
            case '<':  return $cmp <  0;
            case '!=': return $cmp !== 0;
            default:   return $cmp === 0;
        }
    }
}

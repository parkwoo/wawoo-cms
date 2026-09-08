<?php
/**
 * Frontmatter parser (subset of YAML).
 * Supports: scalars, [a, b, c] arrays, "quoted strings".
 * Falls back to a flat key:value scan.
 */

namespace Wawoo\Core;

final class Frontmatter
{
    public static function parse(string $raw): array
    {
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $raw, $m)) {
            return ['meta' => [], 'content' => $raw];
        }
        $meta = [];
        foreach (explode("\n", $m[1]) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, ':') === false) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }
            $meta[$key] = self::parseValue($value);
        }
        return ['meta' => $meta, 'content' => trim($m[2])];
    }

    private static function parseValue(string $v)
    {
        if ($v === '') {
            return '';
        }
        if ($v === 'true')  return true;
        if ($v === 'false') return false;
        if ($v === 'null')  return null;
        if (preg_match('/^\[(.*)\]$/', $v, $m)) {
            $items = array_map(function ($s) {
                return trim(trim($s), '"\'');
            }, explode(',', $m[1]));
            return array_values(array_filter($items, fn($x) => $x !== ''));
        }
        $first = $v[0];
        if (($first === '"' || $first === "'") && substr($v, -1) === $first) {
            return substr($v, 1, -1);
        }
        if (is_numeric($v)) {
            return strpos($v, '.') !== false ? (float)$v : (int)$v;
        }
        return $v;
    }
}

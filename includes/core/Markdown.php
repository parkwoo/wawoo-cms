<?php
/**
 * Markdown rendering wrapper around Parsedown.
 * Adds optional image optimization (lazy / async / fetchpriority).
 */

namespace Wawoo\Core;

final class Markdown
{
    private static $parser;

    public static function render(string $text, bool $optimizeImages = true, bool $figures = true, string $imageBase = ''): string
    {
        if (self::$parser === null) {
            require_once dirname(__DIR__) . '/Parsedown.php';
            self::$parser = new \Parsedown();
            if (method_exists(self::$parser, 'setSafeMode')) {
                self::$parser->setSafeMode(true);
            }
        }
        $html = self::$parser->text($text);
        if ($imageBase !== '') {
            $html = self::resolveRelativeImageSrcs($html, $imageBase);
        }
        if ($optimizeImages) {
            $html = self::optimizeImages($html);
        }
        if ($figures) {
            $html = self::toFigures($html);
        }
        return $html;
    }

    /**
     * Rewrite relative image srcs against a base URL, so a post living in
     * posts/<slug>/index.md can reference ./hero.jpg and have it resolve to
     * /posts/<slug>/hero.jpg. Absolute paths, protocol-relative URLs and
     * data: URIs are left untouched.
     */
    public static function resolveRelativeImageSrcs(string $html, string $base): string
    {
        return preg_replace_callback(
            '#(<img\b[^>]*\bsrc\s*=\s*)(["\'])(.*?)\2#i',
            static function (array $m) use ($base): string {
                $src = $m[3];
                if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|/|data:)#i', $src)) {
                    return $m[0];
                }
                return $m[1] . $m[2] . $base . ltrim($src, '/') . $m[2];
            },
            $html
        );
    }

    private static function optimizeImages(string $html): string
    {
        $i = 0;
        return preg_replace_callback('/<img\b[^>]*>/i', function ($matches) use (&$i) {
            $tag = $matches[0];
            $loading = $i === 0 ? 'eager' : 'lazy';
            $fp = $i === 0 ? 'high' : 'low';
            $attrs = [
                'loading'      => $loading,
                'fetchpriority'=> $fp,
                'decoding'     => 'async',
            ];
            foreach ($attrs as $name => $val) {
                if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*([\'"]).*?\1/i', $tag)) {
                    $tag = preg_replace(
                        '/\b' . preg_quote($name, '/') . '\s*=\s*([\'"]).*?\1/i',
                        $name . '="' . $val . '"',
                        $tag,
                        1
                    );
                } else {
                    $tag = preg_replace('/\s*\/?>$/', ' ' . $name . '="' . $val . '"$0', $tag, 1);
                }
            }
            $i++;
            return $tag;
        }, $html);
    }

    /**
     * Wrap paragraphs that contain exactly one <img> into <figure> with an
     * optional <figcaption> (title > alt) and make the image zoomable via a
     * link to its original src. Images already inside an <a> are left alone.
     */
    private static function toFigures(string $html): string
    {
        return preg_replace_callback(
            '#<p>\s*(<img\b[^>]*>)\s*</p>#i',
            static function (array $m): string {
                $img = $m[1];
                $src = self::attr($img, 'src');
                if ($src === '') {
                    return $m[0];
                }
                $caption = self::attr($img, 'title') ?: self::attr($img, 'alt');
                $zoom = '<a class="post-zoom" href="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '">' . $img . '</a>';
                $fig = $caption !== ''
                    ? '<figcaption>' . htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') . '</figcaption>'
                    : '';
                return '<figure class="post-figure">' . $zoom . $fig . '</figure>';
            },
            $html
        );
    }

    private static function attr(string $tag, string $name): string
    {
        if (preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*([\'"])(.*?)\1/i', $tag, $m)) {
            return html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
        }
        return '';
    }
}

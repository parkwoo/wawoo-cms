<?php
/**
 * Theme loader: provides helpers for the currently active theme.
 * Themes override view templates under themes/<name>/templates/.
 */

namespace Wawoo\Core;

final class Theme
{
    private static ?string $active = null;
    private static ?string $path = null;

    public static function activate(string $name, string $path): void
    {
        self::$active = $name;
        self::$path = $path;
    }

    public static function name(): ?string
    {
        return self::$active;
    }

    public static function path(string $relative = ''): string
    {
        $base = self::$path ?? '';
        if ($relative === '') {
            return $base;
        }
        return $base . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
    }

    public static function asset(string $relative): string
    {
        return '/themes/' . self::$active . '/assets/' . ltrim($relative, '/');
    }

    /**
     * Render a theme template, wrapping it in the theme's header/footer
     * unless the template name starts with "_raw".
     */
    public static function render(string $template, array $vars = []): void
    {
        $file = self::path('templates' . DIRECTORY_SEPARATOR . $template . '.php');
        if (!is_file($file)) {
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo "Theme template not found: " . htmlspecialchars($template);
            return;
        }
        extract($vars, EXTR_SKIP);
        $isRaw = strncmp($template, '_raw', 4) === 0;
        if (!$isRaw && is_file(self::path('templates/_header.php'))) {
            include self::path('templates/_header.php');
        }
        include $file;
        if (!$isRaw && is_file(self::path('templates/_footer.php'))) {
            include self::path('templates/_footer.php');
        }
    }
}

<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Theme;

final class ThemeTest extends TestCase
{
    private string $themeDir;

    protected function setUp(): void
    {
        $this->themeDir = dirname(__DIR__) . '/_tmp/theme_test';
        $this->rm($this->themeDir);
        mkdir($this->themeDir . '/templates', 0755, true);
        mkdir($this->themeDir . '/assets/css', 0755, true);
        file_put_contents($this->themeDir . '/templates/_header.php', "<header><?= \$vars['pageTitle'] ?? 'hdr' ?></header>\n");
        file_put_contents($this->themeDir . '/templates/_footer.php', "<footer>foot</footer>\n");
        file_put_contents($this->themeDir . '/templates/index.php', "<main><?= htmlspecialchars((string)(\$vars['msg'] ?? '')) ?></main>\n");
        file_put_contents($this->themeDir . '/assets/css/style.css', 'body{}');
        Theme::activate('theme_test', $this->themeDir);
    }

    protected function tearDown(): void
    {
        $this->rm($this->themeDir);
    }

    private function rm(string $d): void
    {
        if (!is_dir($d)) {
            return;
        }
        foreach (glob($d . '/*') as $f) {
            if (is_dir($f)) {
                $this->rm($f);
            } else {
                @unlink($f);
            }
        }
        @rmdir($d);
    }

    public function testActivateAndAccessors(): void
    {
        $this->assertSame('theme_test', Theme::name());
        $this->assertSame($this->themeDir, Theme::path());
        $this->assertSame($this->themeDir . '/templates', Theme::path('templates'));
        $this->assertSame('/themes/theme_test/assets/css/style.css', Theme::asset('css/style.css'));
    }

    public function testRenderWrapsWithHeaderFooter(): void
    {
        ob_start();
        Theme::render('index', ['msg' => 'hello']);
        $html = ob_get_clean();
        $this->assertStringContainsString('<header>', $html);
        $this->assertStringContainsString('<main>hello</main>', $html);
        $this->assertStringContainsString('<footer>foot</footer>', $html);
        $this->assertStringContainsString('</header>', $html);
    }

    public function testRenderMissingTemplateReportsError(): void
    {
        ob_start();
        Theme::render('does_not_exist', []);
        $out = ob_get_clean();
        $this->assertStringContainsString('Theme template not found', $out);
    }
}

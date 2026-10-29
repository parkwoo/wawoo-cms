<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Theme;

final class ThemeRenderFileTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = CACHE_DIR . 'theme-render-test.php';
        @mkdir(dirname($this->file), 0755, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @unlink(CACHE_DIR . 'theme-missing-test.php');
    }

    public function testRendersAbsoluteTemplateWithVars(): void
    {
        file_put_contents($this->file, '<?php echo "Hello " . htmlspecialchars($name); ?>');

        ob_start();
        Theme::renderFile($this->file, ['name' => 'World']);
        $out = ob_get_clean();

        $this->assertSame('Hello World', $out);
    }

    public function testIsNotWrappedInFrontEndHeaderFooter(): void
    {
        file_put_contents($this->file, 'ONLY');

        ob_start();
        Theme::renderFile($this->file);
        $out = ob_get_clean();

        $this->assertSame('ONLY', $out);
    }

    public function testMissingTemplateReportsError(): void
    {
        ob_start();
        Theme::renderFile(CACHE_DIR . 'theme-missing-test.php');
        $out = ob_get_clean();

        $this->assertStringContainsString('Template not found', $out);
    }
}

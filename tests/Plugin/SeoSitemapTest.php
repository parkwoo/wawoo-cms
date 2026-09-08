<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SeoSitemapTest extends TestCase
{
    public function testSitemapXmlOutput(): void
    {
        $out = $this->runHandler('/sitemap.xml');
        $this->assertStringStartsWith('<?xml', $out);
        $this->assertStringContainsString('<urlset', $out);
        $this->assertStringContainsString('post/welcome', $out);
    }

    public function testRobotsTxtOutput(): void
    {
        $out = $this->runHandler('/robots.txt');
        $this->assertStringContainsString('User-agent: *', $out);
        $this->assertStringContainsString('Sitemap:', $out);
    }

    public function testIgnoresOtherPaths(): void
    {
        $out = $this->runHandler('/');
        $this->assertSame('', $out);
    }

    private function runHandler(string $path): string
    {
        $bootstrap = dirname(__DIR__, 2) . '/tests/bootstrap.php';
        $code = "<?php\nrequire \$argv[1];\n\$_SERVER['REQUEST_URI'] = \$argv[2];\nob_start();\n(new \\Wawoo\\Plugin\\Seo\\Sitemap())->handle('/');\n\$o = ob_get_clean();\nfwrite(STDOUT, \$o);\n";
        $file = tempnam(sys_get_temp_dir(), 'wfseo') . '.php';
        file_put_contents($file, $code);
        try {
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' '
                 . escapeshellarg($bootstrap) . ' ' . escapeshellarg($path);
            return (string)shell_exec($cmd . ' 2>&1');
        } finally {
            @unlink($file);
        }
    }
}

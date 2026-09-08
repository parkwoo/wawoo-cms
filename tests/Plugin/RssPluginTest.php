<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Rss\Config as RssConfig;
use Wawoo\Plugin\Rss\HeadLink;

final class RssPluginTest extends TestCase
{
    protected function setUp(): void
    {
        $r = new ReflectionProperty(RssConfig::class, 'cache');
        $r->setAccessible(true);
        $r->setValue(null, null);
        unset($GLOBALS['WAWOO_RSS']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['WAWOO_RSS']);
    }

    public function testConfigDefaults(): void
    {
        $cfg = RssConfig::all();
        $this->assertSame(20, $cfg['max_items']);
    }

    public function testConfigHonorsGlobals(): void
    {
        $GLOBALS['WAWOO_RSS'] = ['max_items' => 5];
        $this->assertSame(5, RssConfig::all()['max_items']);
    }

    public function testHeadLinkEmitsAlternate(): void
    {
        ob_start();
        (new HeadLink())->handle([]);
        $out = ob_get_clean();
        $this->assertStringContainsString('type="application/rss+xml"', $out);
        $this->assertStringContainsString(BASE_URL . 'rss', $out);
    }

    public function testFeedOutputsXml(): void
    {
        $out = $this->runFeed('/rss');
        $this->assertStringStartsWith('<?xml', $out);
        $this->assertStringContainsString('<rss version="2.0">', $out);
    }

    public function testFeedIgnoresNonRssPaths(): void
    {
        $out = $this->runFeed('/');
        $this->assertSame('', $out);
    }

    private function runFeed(string $path): string
    {
        $bootstrap = dirname(__DIR__, 2) . '/tests/bootstrap.php';
        $code = "<?php\nrequire \$argv[1];\n\$_SERVER['REQUEST_URI'] = \$argv[2];\nob_start();\n(new \\Wawoo\\Plugin\\Rss\\Feed())->handle('/');\n\$o = ob_get_clean();\nfwrite(STDOUT, \$o);\n";
        $file = tempnam(sys_get_temp_dir(), 'wfrss') . '.php';
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

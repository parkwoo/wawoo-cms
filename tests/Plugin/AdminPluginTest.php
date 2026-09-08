<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Admin\Gate;

final class AdminPluginTest extends TestCase
{
    protected function setUp(): void
    {
        unset($GLOBALS['WAWOO_ADMIN']);
        $_SERVER['REQUEST_URI'] = '/admin/';
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public function testIgnoresNonAdminPaths(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        ob_start();
        (new Gate())->handle('/');
        $out = ob_get_clean();
        $this->assertSame('', $out);
    }

    public function testDisabledWithoutPasswordConstant(): void
    {
        $out = $this->runInSubprocess('no-pass');
        $this->assertStringContainsString('Admin disabled', $out);
    }

    private function runInSubprocess(string $mode): string
    {
        $bootstrap = dirname(__DIR__, 2) . '/tests/bootstrap.php';
        $code = "<?php\nrequire \$argv[1];\n"
            . "if (\$argv[2] === 'no-pass' && defined('ADMIN_PASSWORD')) { return; }\n"
            . "\$_SERVER['REQUEST_URI'] = '/admin/';\n"
            . "ob_start();\n"
            . "(new \\Wawoo\\Plugin\\Admin\\Gate())->handle('" . dirname(__DIR__, 2) . "');\n"
            . "\$o = ob_get_clean();\n"
            . "fwrite(STDOUT, \$o);\n";
        $file = tempnam(sys_get_temp_dir(), 'wfa') . '.php';
        file_put_contents($file, $code);
        try {
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' '
                 . escapeshellarg($bootstrap) . ' ' . escapeshellarg($mode);
            return (string)shell_exec($cmd . ' 2>&1');
        } finally {
            @unlink($file);
        }
    }
}

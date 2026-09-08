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

    public function testPasswordHashCredentialAccepted(): void
    {
        $hash = password_hash('correct-horse', PASSWORD_BCRYPT);
        $out = $this->runLoginSubprocess($hash, 'correct-horse');
        // Correct password passes the gate: no login/error screen. (The
        // isolated subprocess has no activated theme, so it lands on the
        // dashboard render error — which proves auth succeeded.)
        $this->assertStringNotContainsString('Incorrect password', $out);
        $this->assertStringNotContainsString('Admin login', $out);
        $this->assertStringNotContainsString('Admin disabled', $out);
    }

    public function testPasswordHashWrongPasswordRejected(): void
    {
        $hash = password_hash('correct-horse', PASSWORD_BCRYPT);
        $out = $this->runLoginSubprocess($hash, 'wrong');
        $this->assertStringContainsString('Incorrect password', $out);
    }

    private function runLoginSubprocess(string $hash, string $pw): string
    {
        $bootstrap = dirname(__DIR__, 2) . '/tests/bootstrap.php';
        $code = "<?php\nrequire \$argv[1];\n"
            . "\$GLOBALS['WAWOO_ADMIN'] = ['password_hash' => \$argv[2]];\n"
            . "\$_SERVER['REQUEST_URI'] = '/admin/';\n"
            . "\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';\n"
            . "\$_SERVER['REQUEST_METHOD'] = 'POST';\n"
            . "\$_POST = ['pw' => \$argv[3]];\n"
            . "ob_start();\n"
            . "(new \\Wawoo\\Plugin\\Admin\\Gate())->handle('" . dirname(__DIR__, 2) . "');\n"
            . "\$o = ob_get_clean();\n"
            . "fwrite(STDOUT, \$o);\n";
        $file = tempnam(sys_get_temp_dir(), 'wfa') . '.php';
        file_put_contents($file, $code);
        try {
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' '
                 . escapeshellarg($bootstrap) . ' ' . escapeshellarg($hash) . ' ' . escapeshellarg($pw);
            return (string)shell_exec($cmd . ' 2>&1');
        } finally {
            @unlink($file);
        }
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

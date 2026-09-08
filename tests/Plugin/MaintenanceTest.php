<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Maintenance\Config as MaintenanceConfig;
use Wawoo\Plugin\Maintenance\Guard;
use Wawoo\Plugin\Maintenance\Settings;

/**
 * Maintenance plugin tests.
 *
 * Config/Store/Settings behaviour is exercised in-process. Guard::check()
 * exits when it renders, and the plain `cli` SAPI bypasses the guard, so
 * the render/pass-through paths run against a tiny PHP built-in server
 * (SAPI `cli-server`) launched as a subprocess, mirroring the subprocess
 * approach used by the other plugin tests.
 */
final class MaintenanceTest extends TestCase
{
    private const PASSWORD = 's3cret-word';

    private static string $isoDir;
    private static string $host = '127.0.0.1';
    private static int $port;
    private static $proc;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($GLOBALS['WAWOO_MAINTENANCE'], $_COOKIE['wawoo_maintenance']);
        self::cleanRuntimeFiles();
        self::resetConfig();
    }

    protected function tearDown(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($GLOBALS['WAWOO_MAINTENANCE'], $_COOKIE['wawoo_maintenance']);
        self::cleanRuntimeFiles();
        self::resetConfig();
    }

    public static function setUpBeforeClass(): void
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            self::markTestSkipped('Maintenance subprocess tests require CLI PHP.');
        }
        if (!is_executable('/usr/bin/curl') && trim((string)shell_exec('command -v curl 2>/dev/null')) === '') {
            self::markTestSkipped('curl binary is required for maintenance HTTP tests.');
        }
        $probe = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($probe === false) {
            self::markTestSkipped('Sockets unavailable (cannot bind test server): ' . $errstr);
        }
        fclose($probe);
        $base = dirname(__DIR__, 2);
        self::$isoDir = sys_get_temp_dir() . '/wawoo_maint_' . bin2hex(random_bytes(4));
        mkdir(self::$isoDir, 0755, true);

        $router = self::$isoDir . '/router.php';
        file_put_contents($router, self::routerSource($base, self::$isoDir));

        self::$port = self::freePort();
        self::$proc = proc_open(
            [
                PHP_BINARY, '-d', 'display_errors=1',
                '-S', self::$host . ':' . self::$port,
                '-t', self::$isoDir,
                $router,
            ],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', self::$isoDir . '/server.out.log', 'a'],
                2 => ['file', self::$isoDir . '/server.err.log', 'a'],
            ],
            $pipes
        );

        $deadline = microtime(true) + 8.0;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen(self::$host, self::$port, $errno, $errstr, 0.3);
            if ($conn) {
                fclose($conn);
                return;
            }
            usleep(100_000);
        }
        self::markTestSkipped('PHP built-in server did not start for maintenance tests.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$proc)) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
        if (isset(self::$isoDir) && is_dir(self::$isoDir)) {
            self::rmDir(self::$isoDir);
        }
    }

    public function testConfigDefaultsAreOff(): void
    {
        $cfg = MaintenanceConfig::all();
        $this->assertSame(false, $cfg['enabled']);
        $this->assertSame('', $cfg['password_hash']);
        $this->assertSame('wawoo_maintenance', $cfg['cookie_name']);
        $this->assertSame(3600, $cfg['cookie_ttl']);
    }

    public function testEnabledTrueWithoutHashStaysOffInProcess(): void
    {
        // Guard::check() must not render anything when enabled lacks a hash.
        $GLOBALS['WAWOO_MAINTENANCE'] = ['enabled' => true];
        $_SERVER['REQUEST_URI'] = '/';
        self::resetConfig();

        ob_start();
        Guard::check();
        $out = ob_get_clean();

        $this->assertSame('', $out);
    }

    public function testSaveFromPostPersistsPasswordAndEnabled(): void
    {
        $returned = Settings::saveFromPost([
            'enabled'   => '1',
            'password'  => 'hunter2',
            'password2' => 'hunter2',
            'title'     => 'Down for fixes',
            'message'   => 'We will be back shortly.',
            'button'    => 'Retry',
            'footer'    => 'Ops',
        ]);
        $this->assertSame('', (string)Settings::lastError());
        $this->assertTrue($returned['enabled']);
        $this->assertSame('Down for fixes', $returned['title']);

        $file = CACHE_DIR . '/settings/maintenance.json';
        $this->assertFileExists($file);
        $saved = json_decode((string)file_get_contents($file), true);
        $this->assertIsArray($saved);
        $this->assertTrue($saved['enabled']);
        $this->assertNotSame('', $saved['password_hash'] ?? '');
        $this->assertTrue(password_verify('hunter2', (string)$saved['password_hash']));

        self::resetConfig();
        $cfg = MaintenanceConfig::all();
        $this->assertTrue($cfg['enabled']);
        $this->assertSame('Down for fixes', $cfg['title']);
        $this->assertTrue(password_verify('hunter2', (string)$cfg['password_hash']));
    }

    public function testPasswordMismatchKeepsOldHashAndNoWrite(): void
    {
        // Seed an existing hash via the globals override.
        $GLOBALS['WAWOO_MAINTENANCE'] = ['enabled' => true, 'password' => 'old-secret'];
        self::resetConfig();

        $returned = Settings::saveFromPost([
            'enabled'   => '1',
            'password'  => 'new-secret',
            'password2' => 'different',
            'title'     => 'Nope',
        ]);
        $this->assertNotSame('', (string)Settings::lastError());
        // Invalid password pair: nothing is persisted and the previously
        // effective configuration (here from the globals override) is kept.
        $this->assertTrue($returned['enabled']);
        $this->assertFileDoesNotExist(CACHE_DIR . '/settings/maintenance.json');

        self::resetConfig();
        $cfg = MaintenanceConfig::all();
        $this->assertTrue(password_verify('old-secret', (string)$cfg['password_hash']));
    }

    public function testSettingsFileOverridesGlobals(): void
    {
        $GLOBALS['WAWOO_MAINTENANCE'] = ['enabled' => true, 'password' => 'g-pass'];
        self::resetConfig();

        // No "enabled" field => maintenance switched off in the runtime file.
        Settings::saveFromPost([
            'title'   => 'From Settings File',
            'message' => 'Runtime message.',
            'button'  => 'OK',
            'footer'  => 'Runtime footer.',
        ]);
        $this->assertSame('', (string)Settings::lastError());

        self::resetConfig();
        $cfg = MaintenanceConfig::all();
        $this->assertFalse($cfg['enabled']);                 // settings file beats globals
        $this->assertSame('From Settings File', $cfg['title']);
        $this->assertTrue(password_verify('g-pass', (string)$cfg['password_hash']));
    }

    public function testMaintenanceOnRenders503RetryAfterAndPage(): void
    {
        [$status, $body, $headers] = self::request('GET', '/?scenario=on');
        $this->assertSame(503, $status);
        $this->assertTrue(self::hasHeader($headers, 'Retry-After', '3600'));
        $this->assertTrue(self::hasHeader($headers, 'Cache-Control', 'no-store'));
        $this->assertStringContainsString('Maintenance', $body);
        $this->assertStringContainsString('We are performing scheduled maintenance. Please check back soon.', $body);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $body);
        $this->assertStringContainsString('action="/maintenance"', $body);
        $this->assertStringContainsString('name="pw"', $body);
    }

    public function testEnabledWithoutHashAllowsSiteThrough(): void
    {
        [$status, $body] = self::request('GET', '/?scenario=nohash');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('SURVIVED', $body);
        $this->assertStringNotContainsString('mntn-card', $body);
        $this->assertStringNotContainsString('action="/maintenance"', $body);
    }

    public function testDisabledByDefaultAllowsSiteThrough(): void
    {
        [$status, $body] = self::request('GET', '/?scenario=off');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('SURVIVED', $body);
        $this->assertStringNotContainsString('mntn-card', $body);
    }

    public function testExemptAdminPathStaysReachable(): void
    {
        [$status, $body] = self::request('GET', '/admin/?scenario=on');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('SURVIVED', $body);
    }

    public function testAuthorisedCookiePassesThrough(): void
    {
        $token = $this->issueToken();
        $this->assertNotSame('', $token);

        [$status, $body] = self::request('GET', '/?scenario=on', ['wawoo_maintenance' => $token]);
        $this->assertSame(200, $status);
        $this->assertStringContainsString('SURVIVED', $body);
    }

    public function testPostCorrectPasswordRedirectsHome(): void
    {
        [$status, $body, $headers] = self::request(
            'POST',
            '/maintenance?scenario=on',
            [],
            ['pw' => self::PASSWORD]
        );
        $this->assertSame(303, $status, 'body=' . $body . ' headers=' . json_encode($headers));
        $this->assertTrue(self::hasHeader($headers, 'Location', '/'));
    }

    public function testPostWrongPasswordRenders401(): void
    {
        [$status, $body] = self::request(
            'POST',
            '/maintenance?scenario=on',
            [],
            ['pw' => 'wrong-password']
        );
        $this->assertSame(401, $status);
        $this->assertStringContainsString('Incorrect password.', $body);
        $this->assertStringContainsString('action="/maintenance"', $body);
    }

    private function issueToken(): string
    {
        $GLOBALS['WAWOO_MAINTENANCE'] = ['enabled' => true, 'password' => self::PASSWORD];
        self::resetConfig();
        $cfg = MaintenanceConfig::all();
        Guard::authorise($cfg);
        return (string)($_COOKIE[(string)$cfg['cookie_name']] ?? '');
    }

    private static function resetConfig(): void
    {
        $r = new ReflectionClass(MaintenanceConfig::class);
        if ($r->hasProperty('cache')) {
            $p = $r->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    private static function cleanRuntimeFiles(): void
    {
        foreach (glob(CACHE_DIR . 'maint_*.json') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob(CACHE_DIR . 'settings/maintenance.json*') ?: [] as $f) {
            @unlink($f);
        }
    }

    private static function freePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        return (int)substr((string)$name, strrpos((string)$name, ':') + 1);
    }

    private static function request(string $method, string $path, array $cookies = [], array $post = []): array
    {
        $url = 'http://' . self::$host . ':' . self::$port . $path;
        $cmd = [PHP_BINARY === '' ? 'curl' : 'curl', '-s', '-o', '-', '-D', '-', '-w', "\n%{http_code}"];
        if ($method === 'POST') {
            $cmd[] = '-X';
            $cmd[] = 'POST';
            $cmd[] = '-H';
            $cmd[] = 'Content-Type: application/x-www-form-urlencoded';
            $cmd[] = '--data';
            $cmd[] = http_build_query($post);
        } else {
            $cmd[] = '-X';
            $cmd[] = 'GET';
        }
        if ($cookies) {
            $cmd[] = '-b';
            $cmd[] = http_build_query($cookies, '', '; ');
        }
        $cmd[] = $url;
        $out = (string)shell_exec(implode(' ', array_map('escapeshellarg', $cmd)) . ' 2>&1');
        // Split body from headers: last line after the blank separator is the
        // http code; the chunk before the final \r\n\r\n body is headers.
        $code = 0;
        if (preg_match('/\n(\d{3})\s*$/', $out, $m)) {
            $code = (int)$m[1];
        }
        $withoutCode = preg_replace('/\n\d{3}\s*$/', '', $out);
        $parts = explode("\r\n\r\n", $withoutCode, 2);
        $headers = isset($parts[1]) ? explode("\r\n", $parts[0]) : [];
        $body = isset($parts[1]) ? $parts[1] : $withoutCode;
        return [$code, (string)$body, $headers];
    }

    private static function hasHeader(array $headers, string $name, string $expectedValue): bool
    {
        foreach ($headers as $h) {
            $parts = explode(':', $h, 2);
            if (count($parts) === 2 && strcasecmp(trim($parts[0]), $name) === 0) {
                if (trim($parts[1]) === $expectedValue) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function rmDir(string $dir): void
    {
        if (!is_dir($dir) && !is_link($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $p = $dir . '/' . $entry;
            if (is_dir($p) && !is_link($p)) {
                self::rmDir($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }

    private static function routerSource(string $repo, string $iso): string
    {
        $repoQ  = var_export($repo, true);
        $cacheQ = var_export($iso . '/cache/', true);
        $body = <<<PHP
<?php
declare(strict_types=1);
\$repo = {$repoQ};
define('POSTS_DIR', \$repo . '/posts/');
define('CACHE_DIR', {$cacheQ});
define('PLUGINS_DIR', \$repo . '/plugins/');
define('THEMES_DIR', \$repo . '/themes/');
define('BASE_PATH', '/');
define('BASE_URL', 'http://localhost/');
define('SITE_NAME', 'Test Site');
define('SITE_TITLE', 'Test Site');
define('SITE_DESCRIPTION', 'A site used by PHPUnit.');
define('SITE_KEYWORDS', 'test');
define('AUTHOR_NAME', 'Tester');
define('AUTHOR_EMAIL', 'tester@example.com');
define('DEFAULT_LANGUAGE', 'en');
define('DEFAULT_LOCALE', 'en_US');
define('SOCIAL_TWITTER', '');
define('SOCIAL_GITHUB', '');
define('TWITTER_USERNAME', '');
define('GA_TRACKING_ID', '');
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 3600);
define('POSTS_PER_PAGE', 6);
define('DEBUG', false);
define('ENABLED_PLUGINS', ['maintenance']);
define('ENABLED_THEMES', ['default']);
@mkdir(dirname(CACHE_DIR), 0755, true);
@mkdir(CACHE_DIR, 0755, true);
require_once \$repo . '/includes/Parsedown.php';
require_once \$repo . '/includes/core/Hook.php';
require_once \$repo . '/includes/core/Manifest.php';
require_once \$repo . '/includes/core/Autoloader.php';
require_once \$repo . '/includes/core/Registry.php';
require_once \$repo . '/includes/core/Theme.php';
require_once \$repo . '/includes/core/Frontmatter.php';
require_once \$repo . '/includes/core/Markdown.php';
require_once \$repo . '/includes/core/Posts.php';
require_once \$repo . '/includes/core/Cache.php';
require_once \$repo . '/includes/core/Url.php';
require_once \$repo . '/includes/core/PluginManager.php';
require_once \$repo . '/includes/core/Bootstrap.php';
\Wawoo\Core\Autoloader::register();
foreach (glob(\$repo . '/plugins/*/plugin.json') as \$manifestFile) {
    \$raw = json_decode((string)file_get_contents(\$manifestFile), true);
    if (!is_array(\$raw) || empty(\$raw['namespace'])) {
        continue;
    }
    \$src = dirname(\$manifestFile) . '/src';
    if (is_dir(\$src)) {
        \Wawoo\Core\Autoloader::add(\$raw['namespace'], \$src);
    }
}
switch (\$_GET['scenario'] ?? 'off') {
    case 'on':
        \$GLOBALS['WAWOO_MAINTENANCE'] = ['enabled' => true, 'password' => '__MAINT_PW__'];
        break;
    case 'nohash':
        \$GLOBALS['WAWOO_MAINTENANCE'] = ['enabled' => true];
        break;
    case 'off':
    default:
        break;
}
// cli-server keeps the process alive across requests, so the Config
// static cache from an earlier scenario must be dropped each request.
\$r = new ReflectionClass(\Wawoo\Plugin\Maintenance\Config::class);
if (\$r->hasProperty('cache')) {
    \$rp = \$r->getProperty('cache');
    \$rp->setAccessible(true);
    \$rp->setValue(null, null);
}
\Wawoo\Plugin\Maintenance\Guard::check();
echo "SURVIVED\\n";
PHP;
        return str_replace('__MAINT_PW__', self::PASSWORD, $body);
    }
}

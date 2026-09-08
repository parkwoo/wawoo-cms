<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\AdminTotp\Config;
use Wawoo\Plugin\AdminTotp\Csrf;
use Wawoo\Plugin\AdminTotp\Qr;
use Wawoo\Plugin\AdminTotp\Store;
use Wawoo\Plugin\AdminTotp\Totp;

/**
 * Focused regression tests for the admin-totp hardening work:
 * local QR data URIs, CSRF helper behaviour, plugin.json config defaults,
 * temp-dir Store redirects and rate-limit lockouts.
 */
final class AdminTotpHardenTest extends TestCase
{
    private string $tmpFile = '';

    protected function setUp(): void
    {
        $tmp = dirname(__DIR__) . '/_tmp';
        if (!is_dir($tmp)) {
            mkdir($tmp, 0755, true);
        }
        $this->tmpFile = $tmp . '/admin-totp-harden-' . bin2hex(random_bytes(6)) . '.json';
        // Redirect Store writes away from the real plugin data dir.
        $GLOBALS['WAWOO_ADMIN'] = ['data_file' => $this->tmpFile];
        $_SESSION = [];
        self::resetConfig();
    }

    protected function tearDown(): void
    {
        foreach (glob(dirname($this->tmpFile) . '/attempts_*.json') as $f) {
            @unlink($f);
        }
        foreach (glob(dirname($this->tmpFile) . '/attempts_*.json.tmp') as $f) {
            @unlink($f);
        }
        if ($this->tmpFile !== '' && is_file($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
        $tmp = $this->tmpFile . '.tmp';
        if (is_file($tmp)) {
            @unlink($tmp);
        }
        unset($GLOBALS['WAWOO_ADMIN']);
        $_SESSION = [];
        self::resetConfig();
    }

    private static function resetConfig(): void
    {
        $r = new ReflectionClass(Config::class);
        if ($r->hasProperty('cache')) {
            $p = $r->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    // ---- (a) TOTP vectors still hold (guards the untouched core path) ----

    public function testRfc6238VectorStillMatches(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"
        $this->assertSame('287082', Totp::code($secret, 59, 30, 6));
        $this->assertTrue(Totp::verify($secret, Totp::code($secret, time(), 30, 6), 1, 30, 6));
    }

    // ---- (b) qr output is a local data URI with no remote host ----

    public function testQrDataUriIsLocalAndHasNoHttpHost(): void
    {
        $uri = Totp::provisioningUri('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'admin', 'wawoo-cms', 30, 6);
        $qr  = Totp::qrDataUri($uri, 200);

        $this->assertStringStartsWith('data:image/', $qr);
        $this->assertStringNotContainsString('http', $qr);
        $this->assertStringNotContainsString('chart.googleapis.com', $qr);
        $this->assertStringNotContainsString('secret=', $qr);

        $b64 = substr($qr, (int)strpos($qr, ',') + 1);
        $svg = base64_decode($b64, true);
        $this->assertNotFalse($svg);
        $this->assertStringStartsWith('<svg', ltrim((string)$svg));
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', (string)$svg);
    }

    public function testQrModuleMatrixHasConsistentStructure(): void
    {
        $uri = Totp::provisioningUri(Totp::generateSecret(), 'admin', 'wawoo-cms', 30, 6);
        $m = Qr::moduleMatrix($uri, 'M');
        $rows = $m['rows'];
        $this->assertNotEmpty($rows);
        $this->assertSame($m['size'], count($rows));
        $this->assertSame($m['size'], strlen($rows[0]));
        $this->assertSame((int)(($m['size'] - 17) / 4), $m['version']);
        $this->assertContains($m['ecl'], ['M', 'Q'], 'ECL must be M or Q');
    }

    // ---- (c) CSRF helper ----

    public function testCsrfTokenStablePerSession(): void
    {
        $a = Csrf::token();
        $b = Csrf::token();
        $this->assertSame($a, $b);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $a);
        $this->assertTrue(Csrf::check($a));
        $this->assertTrue(Csrf::check($b));
        $this->assertFalse(Csrf::check('not-the-token'));
        $this->assertFalse(Csrf::check(''));
        $this->assertFalse(Csrf::check(null));
    }

    public function testCsrfFieldContainsToken(): void
    {
        $token = Csrf::token();
        $field = Csrf::field();
        $this->assertStringContainsString('name="csrf"', $field);
        $this->assertStringContainsString(htmlspecialchars($token, ENT_QUOTES, 'UTF-8'), $field);
    }

    // ---- (d) Config reads plugin.json defaults ----

    public function testConfigDefaultsComeFromPluginJson(): void
    {
        unset($GLOBALS['WAWOO_ADMIN']);
        self::resetConfig();

        $all = Config::all();
        $this->assertSame(8, $all['backup_codes']);
        $this->assertSame(1800, $all['session_ttl']);
        $this->assertSame(5, $all['max_attempts']);
        $this->assertSame(300, $all['lockout_seconds']);
        $this->assertSame('admin.json', $all['data_file']);
        $this->assertStringEndsWith('cache/admin-totp/admin.json', Config::dataPath());
    }

    public function testGlobalsOverridePluginJsonDefaults(): void
    {
        $GLOBALS['WAWOO_ADMIN'] = ['backup_codes' => 12, 'session_ttl' => 60];
        self::resetConfig();
        $all = Config::all();
        $this->assertSame(12, $all['backup_codes']);
        $this->assertSame(60, $all['session_ttl']);
        // Non-overridden keys still fall back to plugin.json.
        $this->assertSame(5, $all['max_attempts']);
    }

    // ---- Store redirects to the temp data file ----

    public function testStoreEnableDisableRoundTripViaTempDataFile(): void
    {
        $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $this->assertSame($secret, Store::enableTotp($secret));
        $this->assertSame($secret, Store::getSecret());
        $this->assertTrue(Store::hasSecret());
        $this->assertSame(8, Store::remainingBackupCodes());

        // The real plugin data dir must not have been touched.
        $this->assertStringStartsWith(dirname(__DIR__) . '/_tmp/', Config::dataPath());

        Store::disableTotp();
        $this->assertNull(Store::getSecret());
        $this->assertFalse(Store::hasSecret());
    }

    // ---- (e) rate-limit store increments and locks out ----

    public function testRateLimitIncrementsAndLocksOut(): void
    {
        $key = 'attempts_203.0.113.7';
        $max = 3;
        $this->assertFalse(Store::rateLocked($key));

        Store::rateHit($key, $max, 300);
        $this->assertSame(1, Store::rateState($key)['count']);
        $this->assertFalse(Store::rateLocked($key));

        Store::rateHit($key, $max, 300);
        $this->assertSame(2, Store::rateState($key)['count']);

        Store::rateHit($key, $max, 300); // reaches max -> locked
        $this->assertTrue(Store::rateLocked($key));
        $this->assertGreaterThan(time(), Store::rateState($key)['until']);

        // Further attempts while locked do not extend the window.
        $until = Store::rateState($key)['until'];
        Store::rateHit($key, $max, 300);
        $this->assertSame($until, Store::rateState($key)['until']);

        Store::rateClear($key);
        $this->assertFalse(Store::rateLocked($key));
        $this->assertSame(0, Store::rateState($key)['count']);
    }
}

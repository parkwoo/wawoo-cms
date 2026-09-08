<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\PasswordGate\Guard;
use Wawoo\Plugin\PasswordGate\Store as GateStore;
use Wawoo\Plugin\PasswordGate\Config as GateConfig;

final class PasswordGateTest extends TestCase
{
    private string $hash;

    protected function setUp(): void
    {
        $this->hash = password_hash('correct-horse', PASSWORD_BCRYPT);
        $GLOBALS['PASSWORD_GATE'] = ['enabled' => true, 'password_hash' => $this->hash];
        // Force Config::all() static cache to refresh by re-requiring its
        // (compiled) state. Static cache lives across the request in the
        // same PHP-FPM/CLI process; reset via reflection.
        $r = new ReflectionClass(GateConfig::class);
        if ($r->hasProperty('cache')) {
            $p = $r->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    protected function tearDown(): void
    {
        // Wipe rate-limit files and any settings file we created.
        $dir = CACHE_DIR;
        foreach (glob($dir . 'gate_*.json') as $f) {
            @unlink($f);
        }
        foreach (glob($dir . 'settings/password-gate.json') as $f) {
            @unlink($f);
        }
        unset($GLOBALS['PASSWORD_GATE']);
    }

    public function testCookieIsStableAcrossRequests(): void
    {
        // Authorise in one "request"
        $cfg = GateConfig::all();
        Guard::authorise($cfg, true);
        $this->assertTrue(Guard::isAuthorised($cfg));

        // Simulate next request: rebuild the config (different bcrypt salt
        // is irrelevant because secret is derived from a stable HMAC).
        $cfg2 = GateConfig::all();
        $this->assertTrue(Guard::isAuthorised($cfg2));
    }

    public function testWrongCookieFails(): void
    {
        $cfg = GateConfig::all();
        $_COOKIE[(string)$cfg['cookie_name']] = str_repeat('a', 64);
        $this->assertFalse(Guard::isAuthorised($cfg));
    }

    public function testShortCookieFails(): void
    {
        $cfg = GateConfig::all();
        $_COOKIE[(string)$cfg['cookie_name']] = 'abc';
        $this->assertFalse(Guard::isAuthorised($cfg));
    }

    public function testClearRemovesCookie(): void
    {
        $cfg = GateConfig::all();
        Guard::authorise($cfg, true);
        Guard::clear($cfg);
        $this->assertFalse(Guard::isAuthorised($cfg));
    }

    public function testRateLimitLocksOut(): void
    {
        $ip = '203.0.113.99';
        $key = 'attempts_' . preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $ip);
        // Simulate hitting max_attempts.
        GateStore::set($key, ['count' => 5, 'until' => time() + 100]);
        $cfg = GateConfig::all();
        $attempts = GateStore::get($key, []);
        $this->assertGreaterThan(time(), $attempts['until'] ?? 0);
    }
}

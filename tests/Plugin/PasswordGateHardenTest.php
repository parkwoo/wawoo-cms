<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\PasswordGate\Guard;
use Wawoo\Plugin\PasswordGate\Config as GateConfig;

final class PasswordGateHardenTest extends TestCase
{
    private string $hash;

    protected function setUp(): void
    {
        $this->hash = password_hash('correct-horse', PASSWORD_BCRYPT);
        $GLOBALS['PASSWORD_GATE'] = ['password_hash' => $this->hash];
        $r = new ReflectionClass(GateConfig::class);
        if ($r->hasProperty('cache')) {
            $p = $r->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $dir = CACHE_DIR;
        foreach (glob($dir . 'gate_*.json') as $f) {
            @unlink($f);
        }
        unset($GLOBALS['PASSWORD_GATE']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function testCrlfNextIsRejected(): void
    {
        $m = new ReflectionMethod(Guard::class, 'sanitizeNext');
        $m->setAccessible(true);
        $this->assertSame('/', $m->invoke(null, "/x\r\nSet-Cookie: evil=1"));
        $this->assertSame('/', $m->invoke(null, "/x\nInjected: y"));
        $this->assertSame('/', $m->invoke(null, 'http://evil.example/'));
        $this->assertSame('/', $m->invoke(null, '//evil.example/'));
        $this->assertSame('/admin?x=1&y=2#ok', $m->invoke(null, '/admin?x=1&y=2#ok'));
    }

    public function testExpiredCookieReturnsFalse(): void
    {
        $cfg = GateConfig::all();
        Guard::authorise($cfg, true);
        $this->assertTrue(Guard::isAuthorised($cfg));

        $payload = 'exp:' . (time() - 60);
        $token = hash_hmac('sha256', $payload, $cfg['secret'])
            . '.'
            . rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $_COOKIE[(string)$cfg['cookie_name']] = $token;
        $this->assertFalse(Guard::isAuthorised($cfg));
    }

    public function testOldFormatTokenReturnsFalse(): void
    {
        $cfg = GateConfig::all();
        $_COOKIE[(string)$cfg['cookie_name']] = hash_hmac('sha256', 'gate', $cfg['secret']);
        $this->assertFalse(Guard::isAuthorised($cfg));
        $_COOKIE[(string)$cfg['cookie_name']] = str_repeat('a', 64);
        $this->assertFalse(Guard::isAuthorised($cfg));
    }

    public function testSanitizedIpWhenTrustProxy(): void
    {
        $GLOBALS['PASSWORD_GATE'] = [
            'password_hash' => $this->hash,
            'trust_proxy'   => true,
        ];
        $r = new ReflectionClass(GateConfig::class);
        $p = $r->getProperty('cache');
        $p->setAccessible(true);
        $p->setValue(null, null);

        $cfg = GateConfig::all();
        $this->assertTrue($cfg['trust_proxy']);

        $m = new ReflectionMethod(Guard::class, 'clientIp');
        $m->setAccessible(true);

        $_SERVER['REMOTE_ADDR'] = '10.0.0.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '"203.0.113.7", 10.0.0.1';
        $this->assertSame('203.0.113.7', $m->invoke(null, $cfg));

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '  203.0.113.99 , 10.0.0.1  ';
        $this->assertSame('203.0.113.99', $m->invoke(null, $cfg));
    }
}

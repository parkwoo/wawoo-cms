<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\AdminTotp\Totp;

final class TotpTest extends TestCase
{
    private const RFC6238_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890" base32
    private const RFC6238_T0 = 59;

    public function testRfc6238TestVectors(): void
    {
        // Test vectors cross-checked against the official RFC 6238 reference
        // implementation (Python/Java) with the ASCII secret
        // "12345678901234567890" (= base32 "GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ").
        // (unix_time, expected_6_digit_code)
        $cases = [
            [self::RFC6238_T0,         '287082'],
            [self::RFC6238_T0 + 30,    '359152'],
            [self::RFC6238_T0 + 60,    '969429'],
            [self::RFC6238_T0 + 90,    '338314'],
            [self::RFC6238_T0 + 120,   '254676'],
        ];
        foreach ($cases as [$t, $exp6]) {
            $code = Totp::code(self::RFC6238_SECRET, $t, 30, 6);
            $this->assertSame($exp6, $code, "TOTP at t=$t (6 digits)");
        }
    }

    public function testVerifyAcceptsCurrentCode(): void
    {
        $secret = Totp::generateSecret();
        $code   = Totp::code($secret, time(), 30, 6);
        $this->assertTrue(Totp::verify($secret, $code, 1, 30, 6));
    }

    public function testVerifyRejectsBadCode(): void
    {
        $secret = Totp::generateSecret();
        $this->assertFalse(Totp::verify($secret, '000000', 1, 30, 6));
        $this->assertFalse(Totp::verify($secret, '   ',    1, 30, 6));
        $this->assertFalse(Totp::verify($secret, '12',    1, 30, 6));
    }

    public function testWindowAcceptsPreviousAndNext(): void
    {
        $secret  = Totp::generateSecret();
        $past    = Totp::code($secret, time() - 60, 30, 6);
        $future  = Totp::code($secret, time() + 60, 30, 6);
        $this->assertTrue(Totp::verify($secret, $past, 2, 30, 6));
        $this->assertTrue(Totp::verify($secret, $future, 2, 30, 6));
    }

    public function testBase32RoundTrip(): void
    {
        $bytes = random_bytes(32);
        $enc = Totp::base32Encode($bytes);
        $dec = Totp::base32Decode($enc);
        $this->assertSame($bytes, $dec);
    }

    public function testProvisioningUri(): void
    {
        $uri = Totp::provisioningUri('JBSWY3DPEHPK3PXP', 'alice@example.com', 'wawoo-cms', 30, 6);
        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=wawoo-cms', $uri);
    }

    public function testGeneratedSecretIsValid(): void
    {
        $secret = Totp::generateSecret();
        $this->assertNotEmpty($secret);
        $this->assertSame(32, strlen($secret)); // 20 bytes -> 32 base32 chars
        $code = Totp::code($secret, time(), 30, 6);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }
}

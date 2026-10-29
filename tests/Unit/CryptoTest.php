<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Crypto;

final class CryptoTest extends TestCase
{
    private $saved;

    protected function setUp(): void
    {
        $this->saved = $GLOBALS['WAWOO_SECRET_KEY'] ?? null;
        unset($GLOBALS['WAWOO_SECRET_KEY']);
        putenv('WAWOO_SECRET_KEY');
    }

    protected function tearDown(): void
    {
        if ($this->saved === null) {
            unset($GLOBALS['WAWOO_SECRET_KEY']);
        } else {
            $GLOBALS['WAWOO_SECRET_KEY'] = $this->saved;
        }
    }

    public function testPassThroughWithoutKey(): void
    {
        $this->assertSame('plain-secret', Crypto::seal('plain-secret'));
        $this->assertSame('plain-secret', Crypto::open('plain-secret'));
        $this->assertSame('', Crypto::seal(''));
    }

    public function testRoundTripWithKey(): void
    {
        if (!Crypto::available()) {
            $this->markTestSkipped('libsodium not available');
        }
        $GLOBALS['WAWOO_SECRET_KEY'] = base64_encode(random_bytes(32));

        $sealed = Crypto::seal('sk-super-secret');
        $this->assertStringStartsWith('enc:v1:', $sealed);
        $this->assertStringNotContainsString('sk-super-secret', $sealed);
        $this->assertSame('sk-super-secret', Crypto::open($sealed));
    }

    public function testOpenFailsClosedWhenKeyMissing(): void
    {
        if (!Crypto::available()) {
            $this->markTestSkipped('libsodium not available');
        }
        $GLOBALS['WAWOO_SECRET_KEY'] = base64_encode(random_bytes(32));
        $sealed = Crypto::seal('value');
        unset($GLOBALS['WAWOO_SECRET_KEY']);
        $this->assertNull(Crypto::open($sealed));
    }
}

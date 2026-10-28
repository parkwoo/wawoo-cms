<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Mermaid\Config;
use Wawoo\Plugin\Mermaid\Settings;

final class MermaidSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetConfig();
        @unlink(CACHE_DIR . 'settings/mermaid.json');
    }

    protected function tearDown(): void
    {
        $this->resetConfig();
        @unlink(CACHE_DIR . 'settings/mermaid.json');
    }

    private function resetConfig(): void
    {
        $r = new ReflectionProperty(Config::class, 'cache');
        $r->setAccessible(true);
        $r->setValue(null, null);
    }

    public function testRejectsPlainHttpCdn(): void
    {
        $r = Settings::saveFromPost(['enabled' => '1', 'cdn_url' => 'http://cdn.example.com/mermaid.min.js']);
        $this->assertNotSame('', $r['error']);
    }

    public function testAcceptsHttpsAndSameOriginCdn(): void
    {
        $r = Settings::saveFromPost(['enabled' => '1', 'cdn_url' => 'https://cdn.example.com/mermaid.min.js']);
        $this->assertSame('', $r['error']);
        $this->assertSame('https://cdn.example.com/mermaid.min.js', $r['config']['cdn_url']);
        $r2 = Settings::saveFromPost(['enabled' => '1', 'cdn_url' => '/assets/mermaid.min.js']);
        $this->assertSame('', $r2['error']);
    }

    public function testRejectsProtocolRelativeAndBackslashCdn(): void
    {
        // '//host/x.js' is protocol-relative (external), not same-origin.
        foreach (['//evil.example.com/mermaid.min.js', '/\\evil.example.com/x.js', '/%5cevil.example.com/x.js'] as $cdn) {
            $r = Settings::saveFromPost(['enabled' => '1', 'cdn_url' => $cdn]);
            $this->assertNotSame('', $r['error'], "should reject: $cdn");
        }
    }

    public function testRejectsUnknownThemeAndLooseSecurity(): void
    {
        $r = Settings::saveFromPost(['theme' => 'evil', 'cdn_url' => 'https://x.example/m.js']);
        $this->assertNotSame('', $r['error']);
        $r2 = Settings::saveFromPost(['security_level' => 'loose', 'cdn_url' => 'https://x.example/m.js']);
        $this->assertNotSame('', $r2['error']);
    }

    public function testAcceptsStrictAndSandboxSecurity(): void
    {
        $r = Settings::saveFromPost(['security_level' => 'strict', 'cdn_url' => 'https://x.example/m.js']);
        $this->assertSame('', $r['error']);
        $r2 = Settings::saveFromPost(['security_level' => 'sandbox', 'cdn_url' => 'https://x.example/m.js']);
        $this->assertSame('', $r2['error']);
    }

    public function testRejectsMalformedIntegrity(): void
    {
        $r = Settings::saveFromPost(['cdn_integrity' => 'not-a-hash', 'cdn_url' => 'https://x.example/m.js']);
        $this->assertNotSame('', $r['error']);
        $r2 = Settings::saveFromPost(['cdn_integrity' => 'sha384-ABC123+/', 'cdn_url' => 'https://x.example/m.js']);
        $this->assertSame('', $r2['error']);
    }

    public function testSavePersistsAndConfigReadsBack(): void
    {
        Settings::saveFromPost([
            'enabled'       => '1',
            'cdn_url'       => 'https://x.example/mermaid.min.js',
            'cdn_integrity' => 'sha384-AAAA',
            'theme'         => 'forest',
            'dark_theme'    => 'dark',
            'security_level'=> 'strict',
        ]);
        $cfg = Config::all();
        $this->assertTrue($cfg['enabled']);
        $this->assertSame('https://x.example/mermaid.min.js', $cfg['cdn_url']);
        $this->assertSame('sha384-AAAA', $cfg['cdn_integrity']);
        $this->assertSame('forest', $cfg['theme']);
        $this->assertSame('strict', $cfg['security_level']);
    }

    public function testConfigDefaultsPinFixedVersionAndStrict(): void
    {
        $cfg = Config::all();
        $this->assertSame('https://cdn.jsdelivr.net/npm/mermaid@11.17.2/dist/mermaid.min.js', $cfg['cdn_url']);
        $this->assertSame('strict', $cfg['security_level']);
    }
}

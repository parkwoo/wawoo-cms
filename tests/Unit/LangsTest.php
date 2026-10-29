<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Langs;

final class LangsTest extends TestCase
{
    public function testDefaultIsEnglish(): void
    {
        $this->assertSame('en', Langs::default());
    }

    public function testSupportedExcludesDefault(): void
    {
        $supported = Langs::supported();
        $this->assertContains('ja', $supported);
        $this->assertContains('zh', $supported);
        $this->assertNotContains('en', $supported);
    }

    public function testIsSupported(): void
    {
        $this->assertTrue(Langs::isSupported('ja'));
        $this->assertTrue(Langs::isSupported('zh'));
        $this->assertFalse(Langs::isSupported('fr'));
        $this->assertFalse(Langs::isSupported(''));
    }

    public function testCurrentFallsBackToDefault(): void
    {
        $saved = $_GET['mlang'] ?? null;
        $_GET['mlang'] = 'ja';
        $this->assertSame('ja', Langs::current());
        $_GET['mlang'] = 'fr';
        $this->assertSame('en', Langs::current());
        unset($_GET['mlang']);
        $this->assertSame('en', Langs::current());
        if ($saved !== null) {
            $_GET['mlang'] = $saved;
        }
    }

    public function testAlternationMatchesPrefixes(): void
    {
        $alt = Langs::alternation();
        $this->assertMatchesRegularExpression('#^(ja|zh)$#', $alt);
        $this->assertSame(1, preg_match('#^/(' . $alt . ')/post/x$#', '/ja/post/x'));
        $this->assertSame(0, preg_match('#^/(' . $alt . ')/post/x$#', '/fr/post/x'));
    }
}

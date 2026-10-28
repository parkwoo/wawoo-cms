<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Autoloader;

final class AutoloaderTest extends TestCase
{
    private string $tmpDir;
    /** @var array<string,string> */
    private array $savedPrefixes = [];

    protected function setUp(): void
    {
        $p = new ReflectionProperty(Autoloader::class, 'prefixes');
        $p->setAccessible(true);
        $this->savedPrefixes = $p->getValue();
        $this->tmpDir = dirname(__DIR__) . '/_tmp/autoloader';
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') as $f) {
                @unlink($f);
            }
        } else {
            mkdir($this->tmpDir, 0755, true);
        }
        // Create Foo\Bar\Widget at namespace Foo\Bar mapped to tmpDir.
        file_put_contents($this->tmpDir . '/Widget.php', '<?php namespace Foo\\Bar; class Widget {}');
        Autoloader::add('Foo\\Bar', $this->tmpDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
        // Restore prior prefixes so other tests keep plugin autoloading.
        $p = new ReflectionProperty(Autoloader::class, 'prefixes');
        $p->setAccessible(true);
        $p->setValue(null, $this->savedPrefixes);
    }

    public function testLoadFindsMappedClass(): void
    {
        $this->assertTrue(Autoloader::load('Foo\\Bar\\Widget'));
        $this->assertTrue(class_exists('Foo\\Bar\\Widget'));
    }

    public function testLoadReturnsFalseForUnknownPrefix(): void
    {
        $this->assertFalse(Autoloader::load('No\\Such\\Thing'));
    }

    public function testLoadReturnsFalseWhenFileMissing(): void
    {
        $this->assertFalse(Autoloader::load('Foo\\Bar\\Missing'));
    }
}

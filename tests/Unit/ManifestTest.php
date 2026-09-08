<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Manifest;

final class ManifestTest extends TestCase
{
    public function testVersionConstraints(): void
    {
        $this->assertTrue(Manifest::versionMatches('*', '1.2.3'));
        $this->assertTrue(Manifest::versionMatches('>=1.0.0', '1.0.0'));
        $this->assertTrue(Manifest::versionMatches('>=1.0.0', '1.5.0'));
        $this->assertFalse(Manifest::versionMatches('>=2.0.0', '1.5.0'));
        $this->assertTrue(Manifest::versionMatches('<=1.5.0', '1.5.0'));
        $this->assertFalse(Manifest::versionMatches('>1.5.0', '1.5.0'));
        $this->assertTrue(Manifest::versionMatches('<2.0.0', '1.5.0'));
        $this->assertTrue(Manifest::versionMatches('!=1.0.0', '1.0.1'));
        $this->assertFalse(Manifest::versionMatches('!=1.0.0', '1.0.0'));
        $this->assertTrue(Manifest::versionMatches('1.0.0', '1.0.0')); // bare version means =
    }

    public function testLoadRejectsInvalidManifest(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mf');
        file_put_contents($tmp, 'not json');
        $this->assertNull(Manifest::load($tmp));
        @unlink($tmp);
    }

    public function testLoadReadsValidManifest(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mf');
        file_put_contents($tmp, json_encode([
            'name' => 'foo', 'type' => 'plugin', 'version' => '1.0.0',
            'namespace' => 'Foo\\Bar', 'requires' => ['core' => '>=1.0.0'],
            'hooks' => ['x' => 'Y'],
        ]));
        $m = Manifest::load($tmp);
        @unlink($tmp);
        $this->assertNotNull($m);
        $this->assertSame('foo', $m->name);
        $this->assertSame('plugin', $m->type);
        $this->assertSame('1.0.0', $m->version);
        $this->assertSame('Foo\\Bar', $m->namespace);
        $this->assertSame(['core' => '>=1.0.0'], $m->requires);
        $this->assertSame(['x' => 'Y'], $m->hooks);
    }
}

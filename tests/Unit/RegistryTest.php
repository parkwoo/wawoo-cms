<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Hook;
use Wawoo\Core\Registry;
use Wawoo\Core\Autoloader;
use Wawoo\Core\Theme;

final class RegistryTest extends TestCase
{
    private string $root;
    private string $tmpPlugins;
    private string $tmpThemes;

    protected function setUp(): void
    {
        Hook::reset();
        $this->root = dirname(__DIR__, 2);  // = wawoo-cms
        $this->tmpPlugins = $this->root . '/tests/_tmp/registry_plugins';
        $this->tmpThemes  = $this->root . '/tests/_tmp/registry_themes';
        if (is_dir($this->tmpPlugins)) { $this->rm($this->tmpPlugins); }
        if (is_dir($this->tmpThemes))  { $this->rm($this->tmpThemes);  }
        mkdir($this->tmpPlugins, 0755, true);
        mkdir($this->tmpThemes,  0755, true);
    }

    private function rm(string $d): void
    {
        foreach (glob($d . '/*') as $f) {
            if (is_dir($f)) $this->rm($f);
            else @unlink($f);
        }
        @rmdir($d);
    }

    private function makePlugin(string $slug, array $manifest): string
    {
        $dir = sys_get_temp_dir() . '/rt_' . bin2hex(random_bytes(4));
        mkdir($dir . '/src', 0755, true);
        file_put_contents($dir . '/plugin.json', json_encode(array_merge(['type' => 'plugin', 'version' => '1.0.0'], $manifest)));
        if (!empty($manifest['namespace'])) {
            $cls = $manifest['namespace'] . '\\Cb';
            if (!class_exists($cls, false)) {
                eval("namespace {$manifest['namespace']}; class Cb { public function handle() {} }");
            }
        }
        return $dir . '/src';
    }

    public function testDiscoverReadsManifests(): void
    {
        $this->writePluginManifest('p1', ['name' => 'p1', 'namespace' => 'Foo\\P1']);
        $this->writePluginManifest('p2', ['name' => 'p2']);
        $reg = new Registry();
        $reg->discover('plugin', $this->tmpPlugins);
        $items = (new ReflectionProperty($reg, 'items'))->getValue($reg);
        $this->assertArrayHasKey('plugin:p1', $items);
        $this->assertArrayHasKey('plugin:p2', $items);
    }

    public function testResolveReturnsTopologicalOrder(): void
    {
        // p2 depends on p1
        $this->writePluginManifest('p1', ['name' => 'p1', 'namespace' => 'X\\P1', 'requires' => []]);
        $this->writePluginManifest('p2', ['name' => 'p2', 'namespace' => 'X\\P2', 'requires' => ['p1' => '>=1.0.0']]);
        $reg = new Registry();
        $reg->discover('plugin', $this->tmpPlugins);
        $order = $reg->resolve(['p2', 'p1'], 'plugin');
        $this->assertSame('plugin:p1', $order[0]);
        $this->assertSame('plugin:p2', $order[1]);
    }

    public function testCoreDependencyIsVirtual(): void
    {
        $this->writePluginManifest('p', ['name' => 'p', 'namespace' => 'X\\P', 'requires' => ['core' => '>=1.0.0']]);
        $reg = new Registry();
        $reg->discover('plugin', $this->tmpPlugins);
        $reg->boot(['p'], 'plugin');
        $this->assertSame([], $reg->errors());
    }

    public function testVersionMismatchReportsError(): void
    {
        $this->writePluginManifest('p1', ['name' => 'p1', 'namespace' => 'X\\P1', 'version' => '1.0.0']);
        $this->writePluginManifest('p2', ['name' => 'p2', 'namespace' => 'X\\P2', 'requires' => ['p1' => '>=2.0.0']]);
        $reg = new Registry();
        $reg->discover('plugin', $this->tmpPlugins);
        $reg->boot(['p1', 'p2'], 'plugin');
        $errs = $reg->errors();
        $this->assertArrayHasKey('plugin:p2', $errs);
    }

    public function testArrayHookSpecRegistersMultipleHandlers(): void
    {
        $dir = $this->tmpPlugins . '/multi';
        mkdir($dir, 0755, true);
        mkdir($dir . '/src', 0755, true);
        file_put_contents($dir . '/src/HookA.php', '<?php namespace Multi\\P; class HookA { public function handle() { $x = "A"; \\Wawoo\\Core\\Hook::fire("arr_hook_ran", $x); } }');
        file_put_contents($dir . '/src/HookB.php', '<?php namespace Multi\\P; class HookB { public function handle() { $x = "B"; \\Wawoo\\Core\\Hook::fire("arr_hook_ran", $x); } }');
        file_put_contents($dir . '/plugin.json', json_encode([
            'type' => 'plugin',
            'version' => '1.0.0',
            'name' => 'multi',
            'namespace' => 'Multi\\P',
            'hooks' => ['theme.footer' => ['HookA', 'HookB']],
        ]));
        $reg = new Registry();
        $reg->discover('plugin', $this->tmpPlugins);
        $reg->boot(['multi'], 'plugin');
        // Firing theme.footer should invoke both handlers.
        $fired = [];
        Hook::on('arr_hook_ran', function ($who) use (&$fired) { $fired[] = $who; });
        $payload = null;
        Hook::fire('theme.footer', $payload);
        $this->assertContains('A', $fired);
        $this->assertContains('B', $fired);
    }

    private function writePluginManifest(string $slug, array $extra): void
    {
        $dir = $this->tmpPlugins . '/' . $slug;
        mkdir($dir, 0755, true);
        $m = array_merge(['type' => 'plugin', 'version' => '1.0.0'], $extra);
        file_put_contents($dir . '/plugin.json', json_encode($m));
    }
}

<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\PasswordGate\Guard;
use Wawoo\Plugin\PasswordGate\Settings;
use Wawoo\Plugin\PasswordGate\Config as GateConfig;

final class PasswordGateSettingsTest extends TestCase
{
    private string $settingsFile;

    protected function setUp(): void
    {
        $this->settingsFile = rtrim(CACHE_DIR, '/\\') . '/settings/password-gate.json';
        foreach (glob($this->settingsFile) as $f) {
            @unlink($f);
        }
        unset($GLOBALS['PASSWORD_GATE']);
        $this->resetConfigCache();
    }

    protected function tearDown(): void
    {
        foreach (glob(CACHE_DIR . 'settings/password-gate.json') as $f) {
            @unlink($f);
        }
        foreach (glob(CACHE_DIR . 'settings/password-gate.json.*') as $f) {
            @unlink($f);
        }
        unset($GLOBALS['PASSWORD_GATE']);
        unset($_SERVER['REQUEST_URI']);
        $this->resetConfigCache();
    }

    private function resetConfigCache(): void
    {
        $r = new ReflectionClass(GateConfig::class);
        if ($r->hasProperty('cache')) {
            $p = $r->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    private function writeSettings(array $data): void
    {
        $dir = dirname($this->settingsFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->settingsFile, json_encode($data));
    }

    public function testGateIsOffByDefault(): void
    {
        $cfg = GateConfig::all();
        $this->assertFalse($cfg['enabled']);
        $this->assertSame('', $cfg['password_hash']);
    }

    public function testSettingsFileEnabledFalseOverridesGlobals(): void
    {
        $hash = password_hash('correct-horse', PASSWORD_BCRYPT);
        $this->writeSettings(['enabled' => false, 'password_hash' => $hash]);
        $GLOBALS['PASSWORD_GATE'] = ['enabled' => true, 'password_hash' => $hash];
        $this->resetConfigCache();

        $cfg = GateConfig::all();
        $this->assertFalse($cfg['enabled']);
        $this->assertSame($hash, $cfg['password_hash']);
    }

    public function testCheckWithEnabledFalseIsSilent(): void
    {
        $GLOBALS['PASSWORD_GATE'] = [
            'enabled'      => false,
            'password_hash' => password_hash('correct-horse', PASSWORD_BCRYPT),
        ];
        $this->resetConfigCache();
        $_SERVER['REQUEST_URI'] = '/';

        ob_start();
        Guard::check();
        $out = ob_get_clean();

        $this->assertSame('', $out);
        $this->assertFalse(GateConfig::all()['enabled']);
    }

    public function testSaveFromPostPersistsAndConfigPicksItUp(): void
    {
        $result = Settings::saveFromPost([
            'enabled'   => '1',
            'password'  => 'secret-sauce',
            'password2' => 'secret-sauce',
        ]);

        $this->assertTrue($result['enabled']);
        $this->assertFileExists($this->settingsFile);

        $saved = json_decode((string)file_get_contents($this->settingsFile), true);
        $this->assertTrue($saved['enabled']);
        $this->assertTrue(password_verify('secret-sauce', (string)$saved['password_hash']));

        $this->resetConfigCache();
        $cfg = GateConfig::all();
        $this->assertTrue($cfg['enabled']);
        $this->assertTrue(password_verify('secret-sauce', (string)$cfg['password_hash']));
    }

    public function testSaveFromPostRejectsMismatchedPassword(): void
    {
        Settings::saveFromPost([
            'enabled'   => '1',
            'password'  => 'alpha',
            'password2' => 'beta',
        ]);

        $saved = json_decode((string)file_get_contents($this->settingsFile), true);
        $this->assertTrue($saved['enabled']);
        $this->assertArrayNotHasKey('password_hash', $saved);
    }
}

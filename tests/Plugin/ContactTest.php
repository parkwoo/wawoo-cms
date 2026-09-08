<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Contact\Config;
use Wawoo\Plugin\Contact\Form;
use Wawoo\Plugin\Contact\Settings;
use Wawoo\Plugin\Contact\Store;

final class ContactTest extends TestCase
{
    protected function setUp(): void
    {
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
        unset($GLOBALS['WAWOO_CONTACT']);
        $this->resetConfigCache();
        self::cleanupFiles();
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($GLOBALS['WAWOO_CONTACT']);
        $this->resetConfigCache();
        self::cleanupFiles();
    }

    public function testConfigDefaultsComeFromPluginJson(): void
    {
        $cfg = Config::all();
        $this->assertSame('Contact us', $cfg['title']);
        $this->assertSame('', $cfg['recipient_email']);
        $this->assertFalse($cfg['enable_mail']);
        $this->assertTrue($cfg['save_inbox']);
        $this->assertSame(5, $cfg['max_attempts']);
        $this->assertSame(3600, $cfg['rate_window']);
        $this->assertSame('Product inquiry', $cfg['labels']['type_product']);
    }

    public function testConfigGlobalsOverridePluginJson(): void
    {
        $GLOBALS['WAWOO_CONTACT'] = [
            'title'          => 'Ping us',
            'recipient_email' => 'owner@example.com',
            'enable_mail'    => true,
        ];
        $cfg = Config::all();
        $this->assertSame('Ping us', $cfg['title']);
        $this->assertSame('owner@example.com', $cfg['recipient_email']);
        $this->assertTrue($cfg['enable_mail']);
        $this->assertTrue($cfg['save_inbox']);
    }

    public function testConfigSettingsFileOverridesGlobals(): void
    {
        $this->writeSettings(['title' => 'Custom title']);
        $GLOBALS['WAWOO_CONTACT'] = ['title' => 'Global title', 'save_inbox' => false];
        $this->resetConfigCache();

        $cfg = Config::all();
        $this->assertSame('Custom title', $cfg['title']);
        $this->assertFalse($cfg['save_inbox']);
        $this->assertSame(5, $cfg['max_attempts']);
    }

    public function testCsrfTokenStableAndSessionBacked(): void
    {
        $_SESSION = [];
        $t1 = Form::csrfToken();
        $t2 = Form::csrfToken();
        $this->assertSame($t1, $t2);
        $this->assertSame($t1, $_SESSION['wawoo_contact_csrf'] ?? null);
        $this->assertTrue(Form::csrfValid($t1));
    }

    public function testCsrfValidRejectsMismatch(): void
    {
        $_SESSION['wawoo_contact_csrf'] = 'session-token';
        $this->assertTrue(Form::csrfValid('session-token'));
        $this->assertFalse(Form::csrfValid('other'));
        $this->assertFalse(Form::csrfValid(''));
        $this->assertFalse(Form::csrfValid(null));
    }

    public function testValidateAcceptsValidSubmission(): void
    {
        $errors = Form::validate($this->validPost(), Config::labels());
        $this->assertSame([], $errors);
    }

    public function testValidateRejectsMissingFields(): void
    {
        $labels = Config::labels();
        $errors = Form::validate([
            'name'    => '',
            'email'   => '',
            'message' => '',
            'privacy' => '',
        ], $labels);

        $this->assertContains($labels['error_name_required'], $errors);
        $this->assertContains($labels['error_email_required'], $errors);
        $this->assertContains($labels['error_message_required'], $errors);
        $this->assertContains($labels['error_privacy_required'], $errors);
    }

    public function testValidateRejectsBadEmail(): void
    {
        $post = $this->validPost();
        $post['email'] = 'not-an-email';
        $errors = Form::validate($post, Config::labels());
        $this->assertContains(Config::labels()['error_email_invalid'], $errors);
    }

    public function testValidateRejectsOversizedMessage(): void
    {
        $post = $this->validPost();
        $post['message'] = str_repeat('a', 2001);
        $errors = Form::validate($post, Config::labels());
        $this->assertContains(Config::labels()['error_message_too_long'], $errors);
    }

    public function testBuildSubmissionNormalizesAndIncludesTimestamp(): void
    {
        $submission = Form::buildSubmission([
            'name'    => '  Alice  ',
            'email'   => ' ALICE@example.com ',
            'company' => ' Acme ',
            'type'    => 'bogus',
            'message' => '  Hello there  ',
        ]);

        $this->assertSame('Alice', $submission['name']);
        $this->assertSame('ALICE@example.com', $submission['email']);
        $this->assertSame('Acme', $submission['company']);
        $this->assertSame('other', $submission['type']);
        $this->assertSame('Hello there', $submission['message']);
        $this->assertSame($_SERVER['REMOTE_ADDR'], $submission['ip']);
        $this->assertSame('phpunit', $submission['ua']);
        $this->assertIsInt($submission['created_at']);
    }

    public function testRateLimitBlocksAfterMaxAttempts(): void
    {
        $cfg = ['max_attempts' => 3, 'rate_window' => 3600];
        $ip = '203.0.113.50';

        Form::recordAttempt($cfg, $ip);
        Form::recordAttempt($cfg, $ip);
        $this->assertFalse(Form::rateLimited($cfg, $ip));
        $this->assertSame(0, Form::lockoutSeconds($cfg, $ip));

        Form::recordAttempt($cfg, $ip);
        $this->assertTrue(Form::rateLimited($cfg, $ip));
        $this->assertGreaterThan(0, Form::lockoutSeconds($cfg, $ip));
    }

    public function testRateLimitIgnoresExpiredAttempts(): void
    {
        $cfg = ['max_attempts' => 3, 'rate_window' => 3600];
        $ip = '203.0.113.51';
        Store::rateSet($ip, [time() - 7200, time() - 7200, time() - 7200]);

        $this->assertFalse(Form::rateLimited($cfg, $ip));
    }

    public function testStoreSaveAllCountDelete(): void
    {
        $name = Store::save($this->validSubmission());
        $this->assertMatchesRegularExpression('/^inbox-\d+-[0-9a-f]+\.json$/', $name);
        $this->assertFileExists(Store::dir() . $name);

        $this->assertSame(1, Store::count());
        $all = Store::all();
        $this->assertCount(1, $all);
        $this->assertSame('Alice', $all[0]['name']);
        $this->assertSame('Hello', $all[0]['message']);

        $this->assertTrue(Store::delete($name));
        $this->assertSame(0, Store::count());

        $this->assertFalse(Store::delete('../secret.json'));
        $this->assertFalse(Store::delete('not-inbox.json'));
    }

    public function testStoreAllNewestFirst(): void
    {
        Store::save($this->validSubmission());
        usleep(1100000);
        $older = $this->validSubmission();
        $older['message'] = 'Second';
        Store::save($older);

        $all = Store::all();
        $this->assertCount(2, $all);
        $this->assertSame('Second', $all[0]['message']);
    }

    public function testSaveFromPostPersistsRecipientAndToggles(): void
    {
        $result = Settings::saveFromPost([
            'title'          => 'Get in touch',
            'intro'          => 'We reply fast.',
            'recipient_email' => 'owner@example.com',
            'enable_mail'    => '1',
            'save_inbox'     => '1',
            'info_address'   => '1 Main St',
            'info_phone'     => '',
            'info_email'     => 'hello@example.com',
            'info_hours'     => 'Mon-Fri 9-5',
        ]);

        $this->assertNull(Settings::lastError());
        $this->assertSame('owner@example.com', $result['recipient_email']);
        $this->assertTrue($result['enable_mail']);
        $this->assertTrue($result['save_inbox']);
        $this->assertSame('Get in touch', $result['title']);
        $this->assertSame('1 Main St', $result['info']['address']);
        $this->assertSame('', $result['info']['phone']);

        $file = rtrim(CACHE_DIR, '/\\') . '/settings/contact.json';
        $this->assertFileExists($file);
        $saved = json_decode((string)file_get_contents($file), true);
        $this->assertSame('owner@example.com', $saved['recipient_email']);
        $this->assertTrue($saved['enable_mail']);

        $this->resetConfigCache();
        $cfg = Config::all();
        $this->assertSame('Get in touch', $cfg['title']);
        $this->assertTrue($cfg['enable_mail']);
        $this->assertSame('Product inquiry', $cfg['labels']['type_product']);
    }

    public function testSaveFromPostRejectsInvalidRecipientEmail(): void
    {
        $result = Settings::saveFromPost([
            'recipient_email' => 'not-an-email',
            'enable_mail'     => '1',
        ]);

        $this->assertNotNull(Settings::lastError());
        $this->assertNotSame('', (string)Settings::lastError());
        $this->assertSame('', $result['recipient_email']);
        $this->assertFileDoesNotExist(rtrim(CACHE_DIR, '/\\') . '/settings/contact.json');
    }

    public function testSendMailReturnsTrueWhenInboxSaveSucceeds(): void
    {
        $cfg = [
            'enable_mail'    => false,
            'save_inbox'     => true,
            'recipient_email' => '',
        ];
        $this->assertTrue(Form::sendMail($this->validSubmission(), $cfg));
        $this->assertSame(1, Store::count());
    }

    public function testSendMailFalseWhenNothingConfigured(): void
    {
        $cfg = [
            'enable_mail'    => false,
            'save_inbox'     => false,
            'recipient_email' => '',
        ];
        $this->assertFalse(Form::sendMail($this->validSubmission(), $cfg));
        $this->assertSame(0, Store::count());
    }

    private function validPost(): array
    {
        return [
            'name'    => 'Alice',
            'email'   => 'alice@example.com',
            'message' => 'Hello there',
            'privacy' => '1',
            'type'    => 'product',
        ];
    }

    private function validSubmission(): array
    {
        return [
            'name'       => 'Alice',
            'email'      => 'alice@example.com',
            'company'    => '',
            'type'       => 'product',
            'message'    => 'Hello',
            'ip'         => '203.0.113.1',
            'ua'         => 'phpunit',
            'created_at' => time(),
        ];
    }

    private function writeSettings(array $data): void
    {
        $file = rtrim(CACHE_DIR, '/\\') . '/settings/contact.json';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($file, json_encode($data));
    }

    private function resetConfigCache(): void
    {
        $r = new ReflectionClass(Config::class);
        if ($r->hasProperty('cache')) {
            $p = $r->getProperty('cache');
            $p->setAccessible(true);
            $p->setValue(null, null);
        }
    }

    private static function cleanupFiles(): void
    {
        $inbox = rtrim(CACHE_DIR, '/\\') . '/contact';
        if (is_dir($inbox)) {
            foreach (glob($inbox . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($inbox);
        }
        foreach (glob(CACHE_DIR . 'contact_rate_*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob(CACHE_DIR . 'settings/contact.json*') ?: [] as $f) {
            @unlink($f);
        }
    }
}

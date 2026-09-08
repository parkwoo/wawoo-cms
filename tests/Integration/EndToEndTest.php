<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * End-to-end test: boots the real app (router.php + entry points) under
 * PHP's built-in server against an isolated docroot, then drives the full
 * HTTP surface with real requests (home, pretty post URL, 404, search,
 * RSS, sitemap, admin login + authed dashboard).
 */
final class EndToEndTest extends TestCase
{
    private static string $docroot;
    private static string $host = '127.0.0.1';
    private static int $port;
    private static $proc;

    public static function setUpBeforeClass(): void
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            self::markTestSkipped('E2E requires CLI PHP.');
        }
        $probe = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($probe === false) {
            self::markTestSkipped('Sockets unavailable (cannot bind test server): ' . $errstr);
        }
        fclose($probe);
        $base = dirname(__DIR__, 2);
        self::$docroot = dirname(__DIR__) . '/_tmp/e2e_site';
        self::rm(self::$docroot);
        mkdir(self::$docroot, 0755, true);

        // Isolated posts (published + draft) so logic assertions are meaningful.
        mkdir(self::$docroot . '/posts', 0755, true);
        file_put_contents(self::$docroot . '/posts/welcome.md', "---\ntitle: Welcome E2E\ndate: 2026-09-06\ncategory: Meta\ntags: [test, e2e]\nstatus: published\n---\n\nHello **world** from the E2E suite.\n");
        file_put_contents(self::$docroot . '/posts/draft-post.md', "---\ntitle: Draft Nope\ndate: 2026-01-03\ncategory: Meta\nstatus: draft\n---\n\nSecret draft.\n");
        file_put_contents(self::$docroot . '/posts/delete-me.md', "---\ntitle: Delete Me E2E\ndate: 2026-09-07\ncategory: Meta\nstatus: published\n---\n\nDisposable post for the delete tests.\n");
        // Page bundle post: md + images in the same folder.
        mkdir(self::$docroot . '/posts/bundled', 0755, true);
        file_put_contents(self::$docroot . '/posts/bundled/index.md', "---\ntitle: Bundled Post\ndate: 2026-09-05\ncategory: Meta\ntags: [test, e2e]\nstatus: published\ncover: hero.svg\ncover_caption: A hero\n---\n\nBundled content ![fig](hero.svg \"Fig\").\n");
        file_put_contents(self::$docroot . '/posts/bundled/hero.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50"><rect width="100" height="50" fill="#ccc"/></svg>');

        // Local config: admin/rss/search/seo plugins enabled, no password
        // gate and no admin-totp so the isolated flow stays simple.
        $port = self::freePort();
        self::$port = $port;
        file_put_contents(self::$docroot . '/config.local.php', "<?php\n"
            . "define('SITE_NAME', 'E2E Blog');\n"
            . "define('SITE_TITLE', 'E2E Blog');\n"
            . "define('SITE_DESCRIPTION', 'End-to-end test site.');\n"
            . "define('AUTHOR_NAME', 'Tester');\n"
            . "define('BASE_URL', 'http://" . self::$host . ':' . $port . "/');\n"
            . "define('ADMIN_PASSWORD', 'e2e-admin-pass');\n"
            . "define('ENABLED_PLUGINS', ['admin', 'seo', 'rss', 'search', 'menu', 'contact']);\n"
            . "define('ENABLED_THEMES', ['default']);\n"
        );

        // Copy the app code (entry points resolve __DIR__ to the real file
        // location, so symlinks would pull in the real repo config). Copy
        // instead so the isolated config.local.php below actually applies.
        foreach (['index.php', 'post.php', 'page.php', 'dispatch.php', 'router.php',
                  'config.example.php', 'includes', 'themes', 'plugins', 'pages'] as $item) {
            $src = $base . '/' . $item;
            if (is_dir($src)) {
                self::copyDir($src, self::$docroot . '/' . $item);
            } elseif (is_file($src)) {
                copy($src, self::$docroot . '/' . $item);
            }
        }
        // Fresh cache dir owned by the test docroot.
        mkdir(self::$docroot . '/cache', 0755, true);

        // Sensitive real files a vulnerable static passthrough would serve
        // verbatim (draft lists, contact PII inboxes, plugin data).
        if (!is_dir(self::$docroot . '/cache/contact')) {
            mkdir(self::$docroot . '/cache/contact', 0755, true);
        }
        file_put_contents(self::$docroot . '/cache/contact/inbox-1.json',
            '{"name":"Jane","email":"jane@example.com","message":"secret inquiry"}');
        file_put_contents(self::$docroot . '/cache/posts.json', '{"draft-post":"draft body"}');
        if (!is_dir(self::$docroot . '/plugins/comments/data')) {
            mkdir(self::$docroot . '/plugins/comments/data', 0755, true);
        }
        file_put_contents(self::$docroot . '/plugins/comments/data/x.json',
            '{"author":"Troll","email":"troll@example.com","body":"flame"}');

        self::$proc = proc_open(
            [PHP_BINARY, '-d', 'display_errors=1', '-S', self::$host . ':' . $port, '-t', self::$docroot, self::$docroot . '/router.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        // Wait until the server accepts connections.
        $deadline = microtime(true) + 8.0;
        while (microtime(true) < $deadline) {
            $conn = @fsockopen(self::$host, $port, $errno, $errstr, 0.3);
            if ($conn) {
                fclose($conn);
                return;
            }
            usleep(100_000);
        }
        self::markTestSkipped('PHP built-in server did not start: ' . stream_get_contents($pipes[2]));
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$proc)) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
        self::rm(self::$docroot);
    }

    private static function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        foreach (scandir($src) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $s = $src . '/' . $e;
            $d = $dst . '/' . $e;
            if (is_dir($s)) {
                self::copyDir($s, $d);
            } else {
                copy($s, $d);
            }
        }
    }

    private static function freePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        return (int)substr((string)$name, strrpos((string)$name, ':') + 1);
    }

    private static function rm(string $d): void
    {
        if (!is_dir($d) && !is_link($d)) {
            return;
        }
        foreach (scandir($d) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $d . '/' . $e;
            if (is_dir($p) && !is_link($p)) {
                self::rm($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($d);
    }

    /** Perform an HTTP request; returns [status, body, response headers]. */
    private static function request(string $method, string $path, array $cookies = [], array $post = []): array
    {
        $headers = ['Host: ' . self::$host];
        $headerStr = implode("\r\n", $headers) . "\r\n";
        if ($cookies) {
            $headerStr .= 'Cookie: ' . http_build_query($cookies, '', '; ') . "\r\n";
        }
        $opts = [
            'http' => [
                'method' => $method,
                'header' => $headerStr,
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ];
        if ($post) {
            $opts['http']['content'] = http_build_query($post);
            $opts['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        }
        $ctx = stream_context_create($opts);
        $body = @file_get_contents('http://' . self::$host . ':' . self::$port . $path, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                $status = (int)$m[1];
            }
        }
        return [$status, (string)$body, $http_response_header ?? []];
    }

    private static function extractCookies(array $headers): array
    {
        $out = [];
        foreach ($headers as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) {
                $pair = explode(';', substr($h, 12), 2)[0];
                if (strpos($pair, '=') !== false) {
                    [$k, $v] = explode('=', $pair, 2);
                    $out[trim($k)] = trim($v);
                }
            }
        }
        return $out;
    }

    public function testHomepageListsPublishedPosts(): void
    {
        [$status, $body] = self::request('GET', '/');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Welcome E2E', $body);
        $this->assertStringContainsString('Bundled Post', $body);
        $this->assertStringNotContainsString('Draft Nope', $body);
        $this->assertStringContainsString('href="http://' . self::$host . ':' . self::$port . '/post/welcome"', $body);
    }

    public function testHomepageShowsBundleThumbnail(): void
    {
        [$status, $body] = self::request('GET', '/');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('post-card-thumb', $body);
        $this->assertStringContainsString('src="/posts/bundled/hero.svg"', $body);
    }

    public function testBundleImagesAreServedStatically(): void
    {
        [$status] = self::request('GET', '/posts/bundled/hero.svg');
        $this->assertSame(200, $status);
    }

    public function testSensitiveFilesAreNeverServedStatically(): void
    {
        // Raw markdown (drafts/bundles) must not be served verbatim; the
        // router falls through to dispatch, which renders the 404.
        [$mdStatus, $mdBody] = self::request('GET', '/posts/bundled/index.md');
        $this->assertSame(404, $mdStatus);
        $this->assertStringNotContainsString('Bundled content', $mdBody);

        // Existing JSON cache files (draft lists, contact PII inboxes).
        [$inboxStatus] = self::request('GET', '/cache/contact/inbox-1.json');
        $this->assertSame(404, $inboxStatus);
        [$cacheStatus] = self::request('GET', '/cache/posts.json');
        $this->assertSame(404, $cacheStatus);

        // Plugin data files (even existing ones) are never statically served.
        [$pluginStatus] = self::request('GET', '/plugins/comments/data/x.json');
        $this->assertSame(404, $pluginStatus);

        // Includes and config secrets are never served verbatim either.
        [$includeStatus] = self::request('GET', '/includes/core/Bootstrap.php');
        $this->assertSame(404, $includeStatus);
        [$configStatus] = self::request('GET', '/config.local.php');
        $this->assertSame(404, $configStatus);

        // Public bundle media and page-bundle media stay reachable.
        [$svgStatus] = self::request('GET', '/posts/bundled/hero.svg');
        $this->assertSame(200, $svgStatus);
        [$pageSvgStatus] = self::request('GET', '/pages/about/sample.svg');
        $this->assertSame(200, $pageSvgStatus);
    }

    public function testPrettyPostUrlServesPost(): void
    {
        [$status, $body] = self::request('GET', '/post/welcome');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('<p>Hello <strong>world</strong> from the E2E suite.</p>', $body);
    }

    public function testDraftPostIs404Publicly(): void
    {
        [$status] = self::request('GET', '/post/draft-post');
        $this->assertSame(404, $status);
    }

    public function testUnknownSlugIs404(): void
    {
        [$status] = self::request('GET', '/post/does-not-exist');
        $this->assertSame(404, $status);
    }

    public function testSearchReturnsHits(): void
    {
        [$status, $body] = self::request('GET', '/search?q=world');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Welcome E2E', $body);
    }

    public function testRssUsesPrettyPostUrls(): void
    {
        [$status, $body, $headers] = self::request('GET', '/rss');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('<?xml', $body);
        $this->assertStringContainsString('Welcome E2E', $body);
        $this->assertStringContainsString('http://' . self::$host . ':' . self::$port . '/post/welcome', $body);
        $this->assertStringNotContainsString('?post=', $body);
    }

    public function testSitemapUsesPrettyPostUrls(): void
    {
        [$status, $body] = self::request('GET', '/sitemap.xml');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('<urlset', $body);
        $this->assertStringContainsString('/post/welcome', $body);
        $this->assertStringNotContainsString('?post=', $body);
    }

    public function testContactPageRendersAndAcceptsSubmission(): void
    {
        [$status, $body, $headers] = self::request('GET', '/contact');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('name="name"', $body);
        $this->assertStringContainsString('name="email"', $body);
        $this->assertStringContainsString('name="message"', $body);

        // Extract CSRF token from the hidden field.
        if (!preg_match('/name="contact_csrf" value="([a-f0-9]+)"/', $body, $m)) {
            $this->fail('CSRF field missing on contact page.');
        }
        $cookies = self::extractCookies($headers);
        [$status2, $body2] = self::request('POST', '/contact', $cookies, [
            'contact_csrf' => $m[1],
            'name'    => 'E2E Tester',
            'email'   => 'e2e@example.com',
            'company' => 'Acme',
            'message' => 'Hello from the E2E suite.',
            'privacy' => '1',
        ]);
        $this->assertSame(200, $status2);
        $this->assertStringContainsString('thank', strtolower($body2));
    }

    public function testFixedPageServedAtTopLevelUrl(): void
    {
        [$status, $body] = self::request('GET', '/about');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('About wawoo-cms', $body);
        // Images inside the page bundle are served statically.
        [$imgStatus] = self::request('GET', '/pages/about/sample.svg');
        $this->assertSame(200, $imgStatus);
    }

    public function testAdminLoginFlow(): void
    {
        [$status, $body, $h] = self::request('GET', '/admin/');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Admin login', $body);
        $cookies = self::extractCookies($h);

        [$status2, $body2, $h2] = self::request('POST', '/admin/', $cookies, ['pw' => 'e2e-admin-pass']);
        $cookies2 = self::extractCookies($h2);
        // Follow the 303 to the dashboard.
        if (in_array($status2, [301, 302, 303], true)) {
            $loc = '';
            foreach ($h2 as $hh) {
                if (stripos($hh, 'Location:') === 0) {
                    $loc = trim(substr($hh, 9));
                }
            }
            $path = parse_url($loc, PHP_URL_PATH) ?: '/admin/';
            [$status2, $body2] = self::request('GET', $path, $cookies2 ?: $cookies);
        }
        $this->assertSame(200, $status2);
        $this->assertStringContainsString('Dashboard', $body2);
        $this->assertStringContainsString('Welcome E2E', $body2);
    }

    public function testAdminLogoutRequiresPostAndCsrf(): void
    {
        [$cookies] = $this->loginAdmin();
        // GET logout is refused.
        [$status] = self::request('GET', '/admin/?action=logout', $cookies);
        $this->assertSame(403, $status);
        // POST without csrf is refused and session stays alive.
        [$status] = self::request('POST', '/admin/?action=logout', $cookies, []);
        $this->assertSame(403, $status);
        // POST with csrf logs out.
        $csrf = $this->adminCsrf($cookies);
        [$status] = self::request('POST', '/admin/?action=logout', $cookies, ['csrf' => $csrf]);
        $this->assertTrue($status === 200 || $status === 302);
        [$s2, $b2] = self::request('GET', '/admin/', $cookies);
        $this->assertSame(200, $s2);
        $this->assertStringContainsString('Admin login', $b2);
    }

    public function testAdminLoginRegeneratesSessionId(): void
    {
        // Anonymous GET issues one session id; a successful login must
        // issue a DIFFERENT id (session fixation defence).
        $before = $this->sessionIdAfterGet();
        [$s, $b, $h] = self::request('POST', '/admin/', [], ['pw' => 'e2e-admin-pass']);
        $after = $this->sessionIdFromHeaders($h);
        $this->assertSame(200, $s);
        $this->assertNotSame('', $before);
        $this->assertNotSame('', $after);
        $this->assertNotSame($before, $after, 'session id must rotate after login');
    }

    public function testAdminDestructiveActionsRequirePostAndCsrf(): void
    {
        [$cookies] = $this->loginAdmin();

        // GET delete must be refused (403) — never state-changing via GET.
        [$status] = self::request('GET', '/admin/?action=delete&slug=delete-me', $cookies);
        $this->assertSame(403, $status);

        // POST delete without csrf → 403.
        [$status] = self::request('POST', '/admin/?action=delete', $cookies, ['slug' => 'delete-me']);
        $this->assertSame(403, $status);

        // POST delete with csrf → 200/302 and the post disappears.
        $csrf = $this->adminCsrf($cookies);
        [$status, $body, $h] = self::request('POST', '/admin/?action=delete', $cookies, ['slug' => 'delete-me', 'csrf' => $csrf]);
        $this->assertTrue($status === 200 || $status === 302, "delete status was $status body=" . substr($body, 0, 120));
        [$homeStatus, $homeBody] = self::request('GET', '/', $cookies);
        $this->assertStringNotContainsString('Delete Me E2E', $homeBody);
    }

    public function testPluginSettingsRequireCsrfOnPost(): void
    {
        [$cookies] = $this->loginAdmin();
        // Settings GET page renders (contains the hidden csrf field).
        [$status, $body] = self::request('GET', '/admin/?action=settings&plugin=menu', $cookies);
        $this->assertSame(200, $status);
        $this->assertStringContainsString('name="csrf"', $body);
        // POST without csrf must be rejected (not saved).
        [$status] = self::request('POST', '/admin/?action=settings&plugin=menu', $cookies, ['rows' => ['home']]);
        $this->assertNotSame(302, $status); // no successful redirect-save
    }

    /** Log in and return [cookies, csrfToken]. */
    private function loginAdmin(): array
    {
        [$status, $body, $h] = self::request('GET', '/admin/');
        $cookies = self::extractCookies($h);
        [$s2, $b2, $h2] = self::request('POST', '/admin/', $cookies, ['pw' => 'e2e-admin-pass']);
        $cookies2 = self::extractCookies($h2);
        if (in_array($s2, [301, 302, 303], true)) {
            foreach ($h2 as $hh) {
                if (stripos($hh, 'Location:') === 0) {
                    $loc = trim(substr($hh, 9));
                    [$s2, $b2] = self::request('GET', parse_url($loc, PHP_URL_PATH) ?: '/admin/', $cookies2 ?: $cookies);
                }
            }
        }
        $this->assertSame(200, $s2);
        $csrf = '';
        if (preg_match('/name="csrf" value="([^"]+)"/', $b2, $m)) {
            $csrf = $m[1];
        }
        return [$cookies2 ?: $cookies, $csrf];
    }

    private function adminCsrf(array $cookies): string
    {
        [$s, $b] = self::request('GET', '/admin/', $cookies);
        if (preg_match('/name="csrf" value="([^"]+)"/', $b, $m)) {
            return $m[1];
        }
        $this->fail('No csrf token found on dashboard.');
    }

    private function sessionIdFromHeaders(array $headers): string
    {
        foreach ($headers as $hh) {
            if (stripos($hh, 'Set-Cookie: PHPSESSID=') === 0) {
                $pair = explode(';', substr($hh, strlen('Set-Cookie: PHPSESSID=')), 2)[0];
                return trim($pair);
            }
        }
        return '';
    }

    private function sessionIdAfterGet(): string
    {
        [$s, $b, $h] = self::request('GET', '/admin/');
        return $this->sessionIdFromHeaders($h);
    }
}

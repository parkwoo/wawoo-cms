<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Pagination\Config;
use Wawoo\Plugin\Pagination\Pagination;
use Wawoo\Plugin\Pagination\Render;
use Wawoo\Plugin\Pagination\SearchPaginator;

final class PaginationTest extends TestCase
{
    protected function setUp(): void
    {
        $r = new ReflectionProperty(Config::class, 'cache');
        $r->setAccessible(true);
        $r->setValue(null, null);
        unset($GLOBALS['WAWOO_PAGINATION']);
        $_GET = [];
        unset($_SERVER['REQUEST_URI']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['WAWOO_PAGINATION']);
        $_GET = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testWindowPutsEllipsisAtEdges(): void
    {
        $this->assertSame([1, 2, 3, 0, 20], Pagination::window(1, 20, 2));
        $this->assertSame([1, 0, 18, 19, 20], Pagination::window(20, 20, 2));
    }

    public function testWindowPutsEllipsisAroundMiddle(): void
    {
        $this->assertSame([1, 0, 8, 9, 10, 11, 12, 0, 20], Pagination::window(10, 20, 2));
    }

    public function testWindowIsFlatWhenEverythingFits(): void
    {
        $this->assertSame([1, 2, 3], Pagination::window(2, 3, 2));
        $this->assertSame([1, 2, 3, 4, 5], Pagination::window(1, 5, 2));
    }

    public function testPageUrlReplacesPageAndKeepsOtherQueryParams(): void
    {
        $_SERVER['REQUEST_URI'] = '/search?q=php&cat=news&page=3';
        $url = Pagination::pageUrl(2);
        $this->assertStringStartsWith('/search?', $url);
        parse_str((string)parse_url($url, PHP_URL_QUERY), $qs);
        $this->assertSame('php', $qs['q'] ?? null);
        $this->assertSame('news', $qs['cat'] ?? null);
        $this->assertSame('2', $qs['page'] ?? null);
    }

    public function testPageUrlDropsPageParamForFirstPage(): void
    {
        $_SERVER['REQUEST_URI'] = '/search?q=php&page=3';
        $url = Pagination::pageUrl(1);
        $this->assertStringStartsWith('/search?', $url);
        parse_str((string)parse_url($url, PHP_URL_QUERY), $qs);
        $this->assertSame('php', $qs['q'] ?? null);
        $this->assertArrayNotHasKey('page', $qs);
    }

    public function testPageUrlAddsPageParamWhenQueryAbsent(): void
    {
        $_SERVER['REQUEST_URI'] = '/blog';
        $this->assertSame('/blog?page=2', Pagination::pageUrl(2));
        $this->assertSame('/blog', Pagination::pageUrl(1));
    }

    public function testRenderOutputContainsExpectedMarkup(): void
    {
        $_SERVER['REQUEST_URI'] = '/?cat=news&page=10';
        $cfg = ['adjacent' => 2, 'labels' => ['prev' => '&laquo; Prev', 'next' => 'Next &raquo;']];
        $html = Pagination::render(['page' => 10, 'pages' => 20], $cfg);
        $this->assertStringContainsString('<nav class="pagination" role="navigation" aria-label="Pagination">', $html);
        $this->assertStringContainsString('<span class="page-num current" aria-current="page">10</span>', $html);
        $this->assertStringContainsString('<span class="pagination-ellipsis">…</span>', $html);
        $this->assertStringContainsString('&laquo; Prev', $html);
        $this->assertStringContainsString('Next &raquo;', $html);
        $this->assertStringContainsString('href="/?cat=news&amp;page=9"', $html);
        $this->assertStringContainsString('href="/?cat=news&amp;page=11"', $html);
    }

    public function testRenderIsEmptyWhenNothingToPaginate(): void
    {
        $this->assertSame('', Pagination::render(['page' => 1, 'pages' => 1]));
        $this->assertSame('', Pagination::render(['page' => 3, 'pages' => 1]));
    }

    public function testSearchPaginatorSlicesPayloadByReference(): void
    {
        $_GET['page'] = '2';
        $GLOBALS['WAWOO_PAGINATION'] = ['per_page' => 10];
        $posts = [];
        for ($i = 0; $i < 25; $i++) {
            $posts[] = ['slug' => 'post-' . $i];
        }
        $vars = ['q' => 'php', 'posts' => $posts, 'stats' => []];
        (new SearchPaginator())->handle($vars);
        $this->assertCount(10, $vars['posts']);
        $this->assertSame(3, $vars['pages']);
        $this->assertSame(2, $vars['page']);
        $this->assertSame(25, $vars['total']);
        $this->assertSame('post-10', $vars['posts'][0]['slug']);
    }

    public function testSearchPaginatorIsNoOpForSmallResultSet(): void
    {
        $vars = ['q' => 'php', 'posts' => [['slug' => 'a'], ['slug' => 'b']]];
        (new SearchPaginator())->handle($vars);
        $this->assertCount(2, $vars['posts']);
        $this->assertArrayNotHasKey('pages', $vars);
        $this->assertArrayNotHasKey('total', $vars);
    }

    public function testConfigPerPageFallsBackToPostsPerPage(): void
    {
        $this->assertSame(POSTS_PER_PAGE, Config::perPage());
    }

    public function testConfigPerPageHonorsGlobalOverride(): void
    {
        $GLOBALS['WAWOO_PAGINATION'] = ['per_page' => 3];
        $this->assertSame(3, Config::perPage());
    }

    public function testConfigDefaultsFromManifest(): void
    {
        $cfg = Config::all();
        $this->assertSame(0, $cfg['per_page']);
        $this->assertSame(2, $cfg['adjacent']);
        $this->assertSame('&laquo; Prev', $cfg['labels']['prev']);
        $this->assertSame('Next &raquo;', $cfg['labels']['next']);
    }

    public function testRenderHandlerReturnsMarkupOnlyWhenPaginated(): void
    {
        $this->assertNull((new Render())->handle(null, ['page' => 1, 'pages' => 1, 'posts' => []]));
        $html = (new Render())->handle(null, ['page' => 2, 'pages' => 3, 'posts' => [['slug' => 'a'], ['slug' => 'b'], ['slug' => 'c']]]);
        $this->assertIsString($html);
        $this->assertStringContainsString('aria-label="Pagination"', $html);
    }
}

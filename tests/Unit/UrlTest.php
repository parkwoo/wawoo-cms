<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Url;

final class UrlTest extends TestCase
{
    public function testRelativePathPrefixedWithBaseUrl(): void
    {
        $this->assertSame(BASE_URL . 'posts/foo/hero.jpg', Url::absolute('/posts/foo/hero.jpg'));
    }

    public function testAlreadyAbsoluteUrlUntouched(): void
    {
        $this->assertSame('https://e.com/x.png', Url::absolute('https://e.com/x.png'));
    }

    public function testProtocolRelativeUntouched(): void
    {
        $this->assertSame('//cdn.e.com/x.png', Url::absolute('//cdn.e.com/x.png'));
    }

    public function testDataUriUntouched(): void
    {
        $uri = 'data:image/svg+xml;base64,abc';
        $this->assertSame($uri, Url::absolute($uri));
    }

    public function testEmptyStringPassesThrough(): void
    {
        $this->assertSame('', Url::absolute(''));
    }
}

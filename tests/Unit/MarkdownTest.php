<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Core\Markdown;

final class MarkdownTest extends TestCase
{
    public function testRendersParagraphs(): void
    {
        $html = Markdown::render("hello\n\nworld");
        $this->assertStringContainsString('<p>hello</p>', $html);
        $this->assertStringContainsString('<p>world</p>', $html);
    }

    public function testSafeModeEscapesRawScript(): void
    {
        $html = Markdown::render('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testImageGetsLazyAttrsAfterFirst(): void
    {
        $html = Markdown::render("![a](a.png)\n\n![b](b.png)");
        $this->assertStringContainsString('loading="eager"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('decoding="async"', $html);
    }

    public function testExistingAttrsAreOverridden(): void
    {
        $html = Markdown::render('![a](a.png "t")');
        $this->assertStringContainsString('loading="eager"', $html);
    }

    public function testStandaloneImageBecomesFigureWithCaption(): void
    {
        $html = Markdown::render('![alt text](/uploads/x.png "A caption")');
        $this->assertStringContainsString('<figure class="post-figure">', $html);
        $this->assertStringContainsString('<figcaption>A caption</figcaption>', $html);
        $this->assertStringContainsString('class="post-zoom"', $html);
        $this->assertStringContainsString('href="/uploads/x.png"', $html);
        $this->assertStringNotContainsString('<p><img', $html);
    }

    public function testFigureUsesAltWhenNoTitle(): void
    {
        $html = Markdown::render('![only alt](/uploads/y.png)');
        $this->assertStringContainsString('<figcaption>only alt</figcaption>', $html);
    }

    public function testImageInsideLinkIsNotWrapped(): void
    {
        $html = Markdown::render('[![link](/uploads/z.png)](https://example.com)');
        $this->assertStringNotContainsString('post-figure', $html);
        $this->assertStringContainsString('<a href="https://example.com">', $html);
    }

    public function testFiguresDisabled(): void
    {
        $html = Markdown::render('![a](/uploads/n.png "t")', true, false);
        $this->assertStringNotContainsString('post-figure', $html);
        $this->assertStringContainsString('<p><img', $html);
    }

    public function testRelativeImageSrcResolvedAgainstBase(): void
    {
        $html = Markdown::render('![a](hero.jpg)', true, false, '/posts/foo/');
        $this->assertStringContainsString('src="/posts/foo/hero.jpg"', $html);
    }

    public function testAbsoluteImageSrcUntouchedByBase(): void
    {
        $html = Markdown::render('![a](/uploads/x.png) ![b](https://e.com/y.png) ![c](//cdn.e.com/z.png)', true, false, '/posts/foo/');
        $this->assertStringContainsString('src="/uploads/x.png"', $html);
        $this->assertStringContainsString('src="https://e.com/y.png"', $html);
        $this->assertStringContainsString('src="//cdn.e.com/z.png"', $html);
        $this->assertStringNotContainsString('/posts/foo//uploads', $html);
    }
}

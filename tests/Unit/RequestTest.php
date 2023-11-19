<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests\Unit;

use PHPUnit\Framework\TestCase;
use Zaphyr\HttpMessage\Request;
use Zaphyr\HttpMessage\Uri;

class RequestTest extends TestCase
{
    /**
     * @var Request
     */
    protected Request $request;

    protected function setUp(): void
    {
        $this->request = new Request(uri: 'http://example.com');
    }

    protected function tearDown(): void
    {
        unset($this->request);
    }

    /* -------------------------------------------------
     * GET HEADERS
     * -------------------------------------------------
     */

    public function testGetHeadersHasHostHeaderIfUriWithHostIsPresent(): void
    {
        $headers = $this->request->getHeaders();

        self::assertTrue($this->request->hasHeader('Host'));
        self::assertArrayHasKey('Host', $headers);
        self::assertContains('example.com', $headers['Host']);
    }

    public function testGetHeadersHasHostHeaderIfUriWithHostIsDeleted(): void
    {
        $request = $this->request->withoutHeader('host');
        $headers = $request->getHeaders();

        self::assertArrayHasKey('Host', $headers);
        self::assertContains('example.com', $headers['Host']);
    }

    public function testGetHeadersHasNoHostHeaderIfNoUriPresent(): void
    {
        self::assertArrayNotHasKey('Host', (new Request())->getHeaders());
    }

    public function testGetHeadersHasNoHostHeaderIfUriDoesNotContainHost(): void
    {
        self::assertArrayNotHasKey('Host', (new Request(uri: new Uri()))->getHeaders());
    }

    /* -------------------------------------------------
     * GET HEADER
     * -------------------------------------------------
     */

    public function testGetHeaderReturnsUriHostWhenPresent(): void
    {
        self::assertTrue($this->request->hasHeader('Host'));
        self::assertSame(['example.com'], $this->request->getHeader('host'));
    }

    public function testGetHeaderReturnsUriHostWhenHostHeaderDeleted(): void
    {
        $request = $this->request->withoutHeader('host');

        self::assertSame(['example.com'], $request->getHeader('host'));
    }

    public function testGetHeaderReturnsEmptyArrayIfNoUriPresent(): void
    {
        self::assertSame([], (new Request())->getHeader('host'));
    }

    public function testGetHeaderReturnsEmptyArrayIfUriDoesNotContainHost(): void
    {
        self::assertSame([], (new Request(uri: new Uri()))->getHeader('host'));
    }

    /* -------------------------------------------------
     * GET HEADER LINE
     * -------------------------------------------------
     */

    public function testGetHeaderLineReturnsUriHostWhenPresent(): void
    {
        self::assertTrue($this->request->hasHeader('Host'));
        self::assertSame('example.com', $this->request->getHeaderLine('host'));
    }

    public function testGetHeaderLineReturnsEmptyStringIfNoUriPresent(): void
    {
        self::assertEmpty((new Request())->getHeaderLine('host'));
    }

    public function testGetHeaderLineReturnsEmptyStringIfUriDoesNotContainHost(): void
    {
        self::assertEmpty((new Request(uri: new Uri()))->getHeaderLine('host'));
    }

    public function testGetHeaderLineSetFromUriIfNoHostHeaderSpecified(): void
    {
        $request = new Request(uri: 'http://www.example.com');

        self::assertSame('www.example.com', $request->getHeaderLine('host'));
    }

    public function testGetHeaderLineNotSetFromUriIfHostHeaderSpecified(): void
    {
        $request = new Request(uri: 'http://www.example.com', headers: ['Host' => 'www.zaphyr.org']);

        self::assertSame('www.zaphyr.org', $request->getHeaderLine('host'));
    }

    /* -------------------------------------------------
     * WITH URI
     * -------------------------------------------------
     */

    public function testWithUriAndWithPreservedHostDoesNotUpdateHostHeader(): void
    {
        $request = (new Request())->withAddedHeader('Host', 'example.com');
        $newRequest = $request->withUri((new Uri())->withHost('www.example.com'), true);

        self::assertSame('example.com', $newRequest->getHeaderLine('Host'));
    }

    public function testWithUriAndWithoutPreservedHostDoesNotUpdateHostHeader(): void
    {
        $request = (new Request())->withAddedHeader('Host', 'example.com');
        $newRequest = $request->withUri(new Uri());

        self::assertSame('example.com', $newRequest->getHeaderLine('Host'));
    }

    public function testWithUriUpdatesHostAndPortWithPreserveHostDisabledAndNonStandardPort(): void
    {
        $request = (new Request())->withAddedHeader('Host', 'example.com');
        $uri = (new Uri())->withHost('www.example.com')->withPort(8181);
        $newRequest = $request->withUri($uri);

        self::assertSame('www.example.com:8181', $newRequest->getHeaderLine('Host'));
    }
}

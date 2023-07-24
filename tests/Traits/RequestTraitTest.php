<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests\Traits;

use PHPUnit\Framework\TestCase;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Request;
use Zaphyr\HttpMessage\Stream;
use Zaphyr\HttpMessage\Uri;

class RequestTraitTest extends TestCase
{
    /**
     * @var Request
     */
    protected Request $request;

    public function setUp(): void
    {
        $this->request = new Request();
    }

    public function tearDown(): void
    {
        unset($this->request);
    }

    /* -------------------------------------------------
     * CONSTRUCTOR
     * -------------------------------------------------
     */

    public function testConstructorAndGetterMethods(): void
    {
        $method = 'POST';
        $uri = new Uri('https://example.com/');
        $body = new Stream('php://memory');
        $headers = ['x-foo' => ['bar']];
        $protocolVersion = '1.0';

        $request = new Request($method, $uri, $body, $headers, $protocolVersion);

        self::assertSame($uri, $request->getUri());
        self::assertSame('POST', $request->getMethod());
        self::assertSame($body, $request->getBody());
        self::assertSame($protocolVersion, $request->getProtocolVersion());

        $testHeaders = $request->getHeaders();

        foreach ($headers as $key => $value) {
            self::assertArrayHasKey($key, $testHeaders);
            self::assertSame($value, $testHeaders[$key]);
        }
    }

    public function testConstructorThrowsExceptionForInvalidMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Request('INVALID METHOD');
    }

    /**
     * @param string                                      $name
     * @param string|array{0: string, 1: string|string[]} $value
     *
     * @dataProvider headerInjectionVectorsDataProvider
     */
    public function testConstructorThrowsExceptionOnHeadersWithCRLFInjection(string $name, string|array $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Request(headers: [$name => $value]);
    }

    /**
     * @return array<string, array{0: string, 1: string|string[]}>
     */
    public static function headerInjectionVectorsDataProvider(): array
    {
        return [
            'name-with-cr' => ["X-Foo\r-Bar", 'value'],
            'name-with-lf' => ["X-Foo\n-Bar", 'value'],
            'name-with-crlf' => ["X-Foo\r\n-Bar", 'value'],
            'name-with-2crlf' => ["X-Foo\r\n\r\n-Bar", 'value'],
            'name-with-trailing-lf' => ["X-Foo-Bar\n", 'value'],
            'name-with-leading-lf' => ["\nX-Foo-Bar", 'value'],
            'value-with-cr' => ['X-Foo', "value\rinjection"],
            'value-with-lf' => ['X-Foo', "value\ninjection"],
            'value-with-crlf' => ['X-Foo', "value\r\ninjection"],
            'value-with-2crlf' => ['X-Foo', "value\r\n\r\ninjection"],
            'array-value-with-cr' => ['X-Foo', ["value\rinjection"]],
            'array-value-with-lf' => ['X-Foo', ["value\ninjection"]],
            'array-value-with-crlf' => ['X-Foo', ["value\r\ninjection"]],
            'array-value-with-2crlf' => ['X-Foo', ["value\r\n\r\ninjection"]],
            'value-with-trailing-lf' => ['X-Foo', "value\n"],
            'value-with-leading-lf' => ['X-Foo', "\nvalue"],
            'value-with-non-ascii' => ['X-Foo', "value \xFF injection"],
        ];
    }

    /* -------------------------------------------------
     * REQUEST TARGET
     * -------------------------------------------------
     */

    public function testGetRequestTargetIsSlashWhenNoUriPresent(): void
    {
        self::assertSame('/', $this->request->getRequestTarget());
    }

    /**
     * @param Request $request
     * @param string  $expected
     *
     * @dataProvider requestWithUriDataProvider
     */
    public function testGetRequestTargetReturnsTargetWhenPresent(Request $request, string $expected): void
    {
        self::assertSame($expected, $request->getRequestTarget());
    }

    /**
     * @param string $requestTarget
     *
     * @dataProvider validRequestTargetsDataProvider
     */
    public function testWithRequestTarget(string $requestTarget): void
    {
        $request = $this->request->withRequestTarget($requestTarget);

        self::assertNotSame($this->request, $request);
        self::assertSame($requestTarget, $request->getRequestTarget());
    }

    public function testWithRequestTargetThrowsExceptionWhenTargetContainsWhitespace(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->request->withRequestTarget('foo bar');
    }

    /**
     * @return array<string, array<Request, string>>
     */
    public static function requestWithUriDataProvider(): array
    {
        return [
            'absolute' => [new Request(uri:'https://example.com/foo'), '/foo'],
            'relative' => [new Request(uri: '/foo'), '/foo'],
            'absolute-query' => [new Request(uri: 'https://example.com/foo?bar=baz'), '/foo?bar=baz'],
            'relative-query' => [new Request(uri: '/foo?bar=baz'), '/foo?bar=baz'],
        ];
    }

    /**
     * @return array<string, string[]>
     */
    public static function validRequestTargetsDataProvider(): array
    {
        return [
            'asterisk-form' => ['*'],
            'authority-form' => ['example.com'],
            'absolute-form' => ['https://example.com/foo'],
            'absolute-form-query' => ['https://example.com/foo?bar=baz'],
            'origin-form-path-only' => ['/foo'],
            'origin-form' => ['/foo?bar=baz'],
        ];
    }

    /* -------------------------------------------------
     * METHOD
     * -------------------------------------------------
     */

    public function testGetMethodIsGetByDefault(): void
    {
        self::assertSame('GET', $this->request->getMethod());
    }

    public function testWithMethodReturnsNewInstance(): void
    {
        $request = $this->request->withMethod('POST');

        self::assertNotSame($this->request, $request);
        self::assertEquals('POST', $request->getMethod());
    }

    /**
     * @param string $method
     *
     * @dataProvider customRequestMethodsDataProvider
     */
    public function testGetMethodAllowsCustomRequestMethodsThatFollowSpec(string $method): void
    {
        self::assertSame($method, (new Request($method))->getMethod());
    }

    public function testWithMethodThrowsExceptionOnEmptyMethodString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->request->withMethod('');
    }

    /**
     * @return array<string, string[]>
     */
    public static function customRequestMethodsDataProvider(): array
    {
        return [
            'TRACE' => ['TRACE'],
            'PROPFIND' => ['PROPFIND'],
            'PROPPATCH' => ['PROPPATCH'],
            'MKCOL' => ['MKCOL'],
            'COPY' => ['COPY'],
            'MOVE' => ['MOVE'],
            'LOCK' => ['LOCK'],
            'UNLOCK' => ['UNLOCK'],
            '#!ALPHA-1234&%' => ['#!ALPHA-1234&%'],
        ];
    }

    /* -------------------------------------------------
     * URI
     * -------------------------------------------------
     */

    public function testGetUri(): void
    {
        $uri = (new Request('POST'))->getUri();

        self::assertEmpty($uri->getScheme());
        self::assertEmpty($uri->getUserInfo());
        self::assertEmpty($uri->getHost());
        self::assertNull($uri->getPort());
        self::assertEmpty($uri->getPath());
        self::assertEmpty($uri->getQuery());
        self::assertEmpty($uri->getFragment());
    }

    public function testWithUriTargetReturnsNewInstance(): void
    {
        $request = $this->request->withUri(new Uri('https://example.com/foo'));
        $newRequest = $request->withUri(new Uri('https://zaphyr.org'));

        self::assertNotSame($request->getRequestTarget(), $newRequest->getRequestTarget());
        self::assertSame('https://zaphyr.org', (string)$newRequest->getUri());
    }

    public function testWithUriResetsGetRequestTarget(): void
    {
        $request = $this->request->withUri(new Uri('https://example.com/foo'));
        $newRequest = $request->withUri(new Uri('https://zaphyr.org'));

        self::assertNotSame($newRequest->getRequestTarget(), $request->getRequestTarget());
    }

    /**
     * @param string $hostKey
     *
     * @dataProvider hostHeaderKeysDataProvider
     */
    public function testWithUriAndNoHostWillOverwriteHostHeader(string $hostKey): void
    {
        $request = $this->request->withHeader($hostKey, 'example.com');
        $newRequest = $request->withUri(new Uri('http://example.com/foo/bar'));

        self::assertSame('example.com', $newRequest->getHeaderLine('host'));

        $headers = $newRequest->getHeaders();

        self::assertArrayHasKey('Host', $headers);

        if ($hostKey !== 'Host') {
            self::assertArrayNotHasKey($hostKey, $headers);
        }
    }

    /**
     * @return array<string, string[]>
     */
    public static function hostHeaderKeysDataProvider(): array
    {
        return [
            'lowercase' => ['host'],
            'mixed-4' => ['hosT'],
            'mixed-3-4' => ['hoST'],
            'reverse-titlecase' => ['hOST'],
            'uppercase' => ['HOST'],
            'mixed-1-2-3' => ['HOSt'],
            'mixed-1-2' => ['HOst'],
            'titlecase' => ['Host'],
            'mixed-1-4' => ['HosT'],
            'mixed-1-2-4' => ['HOsT'],
            'mixed-1-3-4' => ['HoST'],
            'mixed-1-3' => ['HoSt'],
            'mixed-2-3' => ['hOSt'],
            'mixed-2-4' => ['hOsT'],
            'mixed-2' => ['hOst'],
            'mixed-3' => ['hoSt'],
        ];
    }

    /* -------------------------------------------------
     * GET BODY
     * -------------------------------------------------
     */

    public function testGetBodyDefaultStreamIsWritable(): void
    {
        $this->request->getBody()->write('foo');

        self::assertSame('foo', (string)$this->request->getBody());
    }
}

<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests\Unit;

use PHPUnit\Framework\TestCase;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\ServerRequest;
use Zaphyr\HttpMessage\UploadedFile;
use Zaphyr\HttpMessage\Uri;

class ServerRequestTest extends TestCase
{
    /**
     * @var ServerRequest
     */
    protected ServerRequest $request;

    protected function setUp(): void
    {
        $this->request = new ServerRequest();
    }

    protected function tearDown(): void
    {
        unset($this->request);
    }

    /* -------------------------------------------------
     * CONSTRUCTOR
     * -------------------------------------------------
     */

    public function testConstructorAndGetterMethods(): void
    {
        $method = 'GET';
        $uri = new Uri('http://example.com');
        $headers = ['host' => ['example.com']];
        $protocol = '1.2';
        $serverParams = ['foo' => 'bar'];
        $serverParams['server'] = true;
        $cookieParams = ['baz' => 'qux'];
        $queryParams = ['foo' => 'bar'];
        $uploadedFiles = ['files' => [new UploadedFile('php://temp', 0, 0)]];

        $request = new ServerRequest(
            $method,
            $uri,
            'php://memory',
            $headers,
            $protocol,
            $serverParams,
            $cookieParams,
            $queryParams,
            $uploadedFiles
        );

        self::assertSame($method, $request->getMethod());
        self::assertSame($uri, $request->getUri());
        self::assertSame($headers, $request->getHeaders());
        self::assertSame($protocol, $request->getProtocolVersion());
        self::assertSame($serverParams, $request->getServerParams());
        self::assertSame($cookieParams, $request->getCookieParams());
        self::assertSame($queryParams, $request->getQueryParams());
        self::assertSame($uploadedFiles, $request->getUploadedFiles());
    }

    public function testConstructorThrowsExceptionOnInvalidUploadFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->request = new ServerRequest(uploadedFiles: ['files' => null]);
    }

    /* -------------------------------------------------
     * GET SERVER PARAMS
     * -------------------------------------------------
     */

    public function testGetServerParamsAreEmptyByDefault(): void
    {
        self::assertEmpty($this->request->getServerParams());
    }

    /* -------------------------------------------------
     * COOKIE PARAMS
     * -------------------------------------------------
     */

    public function testGetCookieParamsAreEmptyByDefault(): void
    {
        self::assertEmpty($this->request->getCookieParams());
    }

    public function testWithCookieParamsReturnsNewInstance(): void
    {
        $value = ['foo' => 'bar'];
        $request = $this->request->withCookieParams($value);

        self::assertNotSame($this->request, $request);
        self::assertSame($value, $request->getCookieParams());
    }

    /* -------------------------------------------------
     * QUERY PARAMS
     * -------------------------------------------------
     */

    public function testGetQueryParamsAreEmptyByDefault(): void
    {
        self::assertEmpty($this->request->getQueryParams());
    }

    public function testWithQueryParamsReturnsNewInstance(): void
    {
        $value = ['foo' => 'bar'];
        $request = $this->request->withQueryParams($value);

        self::assertNotSame($this->request, $request);
        self::assertSame($value, $request->getQueryParams());
    }

    /* -------------------------------------------------
     * UPLOADED FILES
     * -------------------------------------------------
     */

    public function testGetUploadedFilesAreEmptyByDefault(): void
    {
        self::assertEmpty($this->request->getUploadedFiles());
    }

    public function testWithUploadedFilesWithNestedUploadedFiles(): void
    {
        $uploadedFiles = [
            new UploadedFile('php://temp', 0, 0),
            new UploadedFile('php://temp', 0, 0),
        ];

        $request = $this->request->withUploadedFiles($uploadedFiles);

        self::assertSame($uploadedFiles, $request->getUploadedFiles());
    }

    public function testWithUploadedFileThrowsExceptionOnInvalidUploadFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->request->withUploadedFiles([null]);
    }

    /* -------------------------------------------------
     * PARSED BODY
     * -------------------------------------------------
     */

    public function testGetParsedBodyIsNullByDefault(): void
    {
        self::assertNull($this->request->getParsedBody());
    }

    public function testWithParsedBodyReturnsNewInstance(): void
    {
        $value = ['foo' => 'bar'];
        $request = $this->request->withParsedBody($value);

        self::assertNotSame($this->request, $request);
        self::assertSame($value, $request->getParsedBody());
    }

    public function testWithParsedBodyThrowsExceptionOnInvalidData(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->request->withParsedBody('nope');
    }

    /* -------------------------------------------------
     * ATTRIBUTES
     * -------------------------------------------------
     */

    public function testGetAttributesAreEmptyByDefault(): void
    {
        self::assertEmpty($this->request->getAttributes());
    }

    public function testGetAttributeIsNullByDefault(): void
    {
        self::assertNull($this->request->getAttribute('nope'));
    }

    public function testGetAttributeCustomDefaultValue(): void
    {
        $default = 'foo';
        self::assertEquals($default, $this->request->getAttribute('nope', $default));
    }

    public function testWithAttributeReturnsNewInstance(): void
    {
        $request = $this->request->withAttribute('foo', 'bar');

        self::assertNotSame($this->request, $request);
        self::assertSame('bar', $request->getAttribute('foo'));
    }

    public function testWithoutAttributeReturnsNewInstance(): void
    {
        $this->request->withAttribute('foo', 'bar');
        $request = $this->request->withoutAttribute('foo');

        self::assertNotSame($this->request, $request);
        self::assertNull($request->getAttribute('foo'));
    }

    public function testWithoutAttributeAllowsRemovingAttributeWithNullValue(): void
    {
        $request = $this->request->withAttribute('foo', null);
        $request = $request->withoutAttribute('foo');

        self::assertSame([], $request->getAttributes());
    }

    public function testWithoutAttributeRemovesNonExistentAttribute(): void
    {
        $request = $this->request->withoutAttribute('boo');

        self::assertSame([], $request->getAttributes());
    }
}

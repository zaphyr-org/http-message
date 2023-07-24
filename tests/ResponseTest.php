<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests;

use PHPUnit\Framework\TestCase;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Response;
use Zaphyr\HttpMessage\Stream;

class ResponseTest extends TestCase
{
    /**
     * @var Response
     */
    protected Response $response;

    public function setUp(): void
    {
        $this->response = new Response();
    }

    public function tearDown(): void
    {
        unset($this->response);
    }

    /* -------------------------------------------------
     * CONSTRUCTOR
     * -------------------------------------------------
     */

    public function testConstructor(): void
    {
        $response = new Response(
            $body = new Stream('php://memory'),
            $status = 302,
            $headers = ['location' => ['https://example.com/']]
        );

        self::assertSame($body, $response->getBody());
        self::assertSame($status, $response->getStatusCode());
        self::assertSame($headers, $response->getHeaders());
    }

    public function testConstructorThrowsExceptionOnInvalidBody(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response(['nope']);
    }

    public function testConstructorThrowsExceptionOnInvalidHeaders(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response(headers: [[['X-Foo']], 'header name']);
    }

    /**
     * @param string                                      $name
     * @param string|array{0: string, 1: string|string[]} $value
     *
     * @dataProvider headerInjectionVectorsDataProvider
     */
    public function testConstructorThrowsExceptionOnCRLFInjection(string $name, string|array $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Response(headers: [$name => $value]);
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
     * STATUS CODE
     * -------------------------------------------------
     */

    public function testGetStatusCodeIs200ByDefault(): void
    {
        self::assertSame(200, $this->response->getStatusCode());
    }

    public function testWithStatusReturnsNewInstance(): void
    {
        $response = $this->response->withStatus(400);

        self::assertNotSame($this->response, $response);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testWithStatusCanSetCustomReasonPhrase(): void
    {
        $response = $this->response->withStatus(422, 'Foo');

        self::assertSame('Foo', $response->getReasonPhrase());
    }

    /**
     * @param int $statusCode
     *
     * @dataProvider invalidStatusCodesDataProvider
     */
    public function testWithStatusCodeThrowsExceptionOnInvalidStatusCode(int $statusCode): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withStatus($statusCode);
    }

    /**
     * @return int[][]
     */
    public static function invalidStatusCodesDataProvider(): array
    {
        return [
            [99],
            [600],
        ];
    }

    /* -------------------------------------------------
     * REASON PHRASE
     * -------------------------------------------------
     */

    public function testGetReasonPhraseIsOKByDefault(): void
    {
        self::assertSame('OK', $this->response->getReasonPhrase());
    }

    public function testGetReasonPhraseIsAffectedByStatusCode(): void
    {
        $response = $this->response->withStatus(404);

        self::assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testGetReasonPhraseEmptyStringIsAffectedByStatusCode(): void
    {
        $response = $this->response->withStatus(404, '');

        self::assertSame('Not Found', $response->getReasonPhrase());
    }
}

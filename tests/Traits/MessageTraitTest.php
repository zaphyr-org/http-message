<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests\Traits;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Response;

class MessageTraitTest extends TestCase
{
    /**
     * @var Response
     */
    protected Response $response;

    /**
     * @var StreamInterface
     */
    protected StreamInterface $stream;

    public function setUp(): void
    {
        $this->response = new Response();
        $this->streamMock = $this->createMock(StreamInterface::class);
    }

    public function tearDown(): void
    {
        unset($this->response, $this->streamMock);
    }

    /* -------------------------------------------------
     * PROTOCOL VERSION
     * -------------------------------------------------
     */

    public function testGetProtocolVersionReturnsDefaultProtocolVersion(): void
    {
        self::assertEquals('1.1', $this->response->getProtocolVersion());
    }

    public function testWithProtocolVersionReturnsNewInstance(): void
    {
        $message = $this->response->withProtocolVersion('1.0');

        self::assertNotSame($this->response, $message);
        self::assertEquals('1.0', $message->getProtocolVersion());
    }

    public function testWithProtocolVersionReturnsSameInstanceWhenProtocolVersionsAreSame(): void
    {
        self::assertSame($this->response, $this->response->withProtocolVersion('1.1'));
    }

    public function testWithProtocolVersionThrowsExceptionWhenProtocolVersionIsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withProtocolVersion('');
    }

    /**
     * @param string $version
     *
     * @dataProvider validProtocolVersionProvider
     */
    public function testWithProtocolVersionReturnsNewInstanceOnValidProtocolVersion(string $version): void
    {
        $message = $this->response->withProtocolVersion($version);

        self::assertNotSame($this->response, $message);
        self::assertEquals($version, $message->getProtocolVersion());
    }

    /**
     * @param string $version
     *
     * @dataProvider invalidProtocolVersionProvider
     */
    public function testWithProtocolVersionThrowsExceptionOnInvalidProtocolVersion(string $version): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withProtocolVersion($version);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function validProtocolVersionProvider(): array
    {
        return [
            '1.0' => ['1.0'],
            '2' => ['2'],
            '2.0' => ['2.0'],
            '3' => ['3'],
            '3.0' => ['3.0'],
        ];
    }

    /**
     * @return array<string,  array<int, string>>
     */
    public static function invalidProtocolVersionProvider(): array
    {
        return [
            '1' => ['1'],
            '1.2' => ['1.2'],
            '1.1.1' => ['1.1.1'],
        ];
    }

    /* -------------------------------------------------
     * HEADERS
     * -------------------------------------------------
     */

    public function testGetHeadersReturnsEmptyArrayByDefault(): void
    {
        self::assertSame([], $this->response->getHeaders());
    }

    public function testHasHeader(): void
    {
        $message = $this->response->withHeader('X-Foo', 'Foo');

        self::assertNotSame($message, $this->response);
        self::assertTrue($message->hasHeader('X-Foo'));
    }

    public function testGetHeader(): void
    {
        $message = $this->response->withHeader('X-Foo', 'Foo');

        self::assertNotSame($message, $this->response);
        self::assertSame(['Foo'], $message->getHeader('X-Foo'));
    }

    public function testGetHeaderReturnsEmptyArrayWhenHeaderDoesNotExist(): void
    {
        self::assertSame([], $this->response->getHeader('X-Foo'));
    }

    public function testGetHeaderLine(): void
    {
        $message = $this->response->withHeader('X-Foo', ['Foo', 'Bar']);

        self::assertNotSame($message, $this->response);
        self::assertSame('Foo,Bar', $message->getHeaderLine('X-Foo'));
    }

    public function testGetHeaderLineReturnsEmptyStringWhenHeaderDoesNotExist(): void
    {
        self::assertSame('', $this->response->getHeaderLine('X-Foo'));
    }

    public function testWithHeader(): void
    {
        $message = $this->response->withHeader('X-Foo', ['Foo', 'Bar']);

        self::assertNotSame($message, $this->response);
        self::assertTrue($message->hasHeader('X-Foo'));
        self::assertFalse($message->hasHeader('X-Bar'));
    }

    public function testWithHeaderCanReplaceValue(): void
    {
        $message = $this->response->withHeader('X-Foo', 'Foo');
        $newMessage = $message->withHeader('X-Foo', 'Bar');

        self::assertSame('Bar', $newMessage->getHeaderLine('X-Foo'));
    }

    public function testWithHeaderCanHandleNumericalHeaderNames(): void
    {
        $message = $this->response->withHeader('123', 'Foo');

        self::assertSame(['123' => ['Foo']], $message->getHeaders());
    }

    public function testWithHeaderCanHandeNumericalHeaderValues(): void
    {
        $message = $this->response->withHeader('X-Foo', [1]);
        $newMessage = $this->response->withHeader('X-Bar', [1.2]);

        self::assertSame(['X-Foo' => ['1']], $message->getHeaders());
        self::assertSame(['X-Bar' => ['1.2']], $newMessage->getHeaders());
    }

    /**
     * @param string $value
     *
     * @dataProvider whitespaceHeaderDataProvider
     */
    public function testWithHeaderTrimsWhitespace(string $value): void
    {
        $message = $this->response->withHeader('X-Foo', $value);

        self::assertSame(trim($value, "\t "), $message->getHeaderLine('X-Foo'));
    }

    /**
     * @param string $value
     *
     * @dataProvider headerValuesControlCharactersDataProvider
     */
    public function testWithHeaderNotContainNewlines(string $value): void
    {
        $message = $this->response->withHeader('X-Foo', $value);

        self::assertStringNotContainsString("\r", $message->getHeaderLine('X-Foo'));
        self::assertStringNotContainsString("\n", $message->getHeaderLine('X-Foo'));
        self::assertStringContainsString(' ', $message->getHeaderLine('X-Foo'));
    }

    public function testWithHeaderThrowsExceptionOnInvalidHeaderValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withHeader('X-Foo', ['Foo' => ['Bar']]);
    }

    public function testWithHeaderThrowsExceptionOnEmptyStringName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withHeader('', 'Bar');
    }

    /**
     * @param string                                      $name
     * @param string|array{0: string, 1: string|string[]} $value
     *
     * @dataProvider headerInjectionVectorsDataProvider
     */
    public function testWithHeaderThrowsExceptionOnCRLFInjection(string $name, string|array $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withHeader($name, $value);
    }

    /**
     * @param mixed $value
     *
     * @dataProvider invalidArrayHeaderValuesDataProvider
     */
    public function testWithHeaderThrowsExceptionOnInvalidHeaderValuesInArrays(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withHeader('X-Foo', [$value]);
    }

    public function testWithHeaderThrowsExceptionOnEmptyArrayValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withHeader('X-Foo', []);
    }

    public function testWithAddedHeader(): void
    {
        $message = $this->response->withHeader('X-Foo', 'Foo');
        $newMessage = $message->withAddedHeader('X-Foo', ['Bar']);

        self::assertNotSame($newMessage, $message);
        self::assertSame('Foo,Bar', $newMessage->getHeaderLine('X-Foo'));
    }

    public function testWithAddedHeaderIsCaseInsensitive(): void
    {
        $message = $this->response->withHeader('X-Foo', 'Foo')->withAddedHeader('x-foo', 'Bar');

        self::assertNotSame($message, $this->response);
        self::assertSame(['X-Foo' => ['Foo', 'Bar']], $message->getHeaders());
    }

    public function testWithAddedHeaderCanHandleNumericalHeaderNames(): void
    {
        $message = $this->response->withAddedHeader('123', 'Foo');

        self::assertSame(['123' => ['Foo']], $message->getHeaders());
    }

    public function testWithAddedHeaderCanHandeNumericalHeaderValues(): void
    {
        $message = $this->response->withAddedHeader('X-Foo', [1]);
        $newMessage = $this->response->withAddedHeader('X-Bar', [1.2]);

        self::assertSame(['X-Foo' => ['1']], $message->getHeaders());
        self::assertSame(['X-Bar' => ['1.2']], $newMessage->getHeaders());
    }

    /**
     * @param string $value
     *
     * @dataProvider whitespaceHeaderDataProvider
     */
    public function testWithAddedHeaderTrimsWhitespace(string $value): void
    {
        $message = $this->response->withAddedHeader('X-Foo', $value);

        self::assertSame(trim($value, "\t "), $message->getHeaderLine('X-Foo'));
    }

    /**
     * @param string $value
     *
     * @dataProvider headerValuesControlCharactersDataProvider
     */
    public function testWithAddedHeaderNotContainNewlines(string $value): void
    {
        $message = $this->response->withAddedHeader('X-Foo', $value);

        self::assertStringNotContainsString("\r", $message->getHeaderLine('X-Foo'));
        self::assertStringNotContainsString("\n", $message->getHeaderLine('X-Foo'));
        self::assertStringContainsString(' ', $message->getHeaderLine('X-Foo'));
    }

    public function testWithAddedHeaderThrowsExceptionOnInvalidHeaderValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withAddedHeader('X-Foo', ['Foo' => ['Bar']]);
    }

    public function testWithAddedHeaderThrowsExceptionOnEmptyStringName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withAddedHeader('', 'Bar');
    }

    /**
     * @param string                                      $name
     * @param string|array{0: string, 1: string|string[]} $value
     *
     * @dataProvider headerInjectionVectorsDataProvider
     */
    public function testWithAddedHeaderThrowsExceptionOnCRLFInjection(string $name, string|array $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withAddedHeader($name, $value);
    }

    /**
     * @param mixed $value
     *
     * @dataProvider invalidArrayHeaderValuesDataProvider
     */
    public function testWithAddedHeaderThrowsExceptionOnInvalidHeaderValuesInArrays(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withAddedHeader('X-Foo', [$value]);
    }

    public function testWithAddedHeaderThrowsExceptionOnEmptyArrayValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->response->withAddedHeader('X-Foo', []);
    }

    public function testWithoutHeader(): void
    {
        $message = $this->response->withHeader('X-Foo', 'Foo');

        self::assertTrue($message->hasHeader('X-Foo'));

        $newMessage = $message->withoutHeader('X-Foo');

        self::assertNotSame($newMessage, $message);
        self::assertFalse($newMessage->hasHeader('X-Foo'));
    }

    public function testWithoutHeaderIsCaseInsensitive(): void
    {
        $message = $this->response->withHeader('X-Foo', 'Foo')
            ->withAddedHeader('x-foo', 'Bar')
            ->withAddedHeader('X-FOO', 'Baz');

        self::assertTrue($message->hasHeader('x-foo'));

        $newMessage = $message->withoutHeader('x-foo');

        self::assertFalse($newMessage->hasHeader('X-Foo'));
    }

    public function testWithoutHeaderReturnsSameInstanceWhenHeaderDoesNotExist(): void
    {
        $message = $this->response->withoutHeader('X-Foo');

        self::assertSame($this->response, $message);
    }

    /**
     * @return array<string, string[]>
     */
    public static function whitespaceHeaderDataProvider(): array
    {
        return [
            'no' => ["Baz"],
            'leading' => [" Baz"],
            'trailing' => ["Baz "],
            'both' => [" Baz "],
            'mixed' => [" \t Baz\t \t"],
        ];
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

    /**
     * @return array<string, array<mixed>>
     */
    public static function invalidArrayHeaderValuesDataProvider(): array
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'object' => [(object)['header' => ['foo', 'bar']]],
            'array' => [['array']],
        ];
    }

    /**
     * @return array<string, string[]>
     */
    public static function headerValuesControlCharactersDataProvider(): array
    {
        return [
            'space' => ["foo\r\n bar"],
            'tab' => ["foo\r\n\tbar"],
        ];
    }

    /* -------------------------------------------------
     * BODY
     * -------------------------------------------------
     */

    public function testGetBodyReturnsDefaultStream(): void
    {
        self::assertSame('', (string)$this->response->getBody());
    }

    public function testWithBodyReturnsNewInstance(): void
    {
        $message = $this->response->withBody($this->streamMock);

        self::assertNotSame($this->response, $message);
        self::assertSame('', (string)$message->getBody());
    }

    public function testWithBodyReturnsSameInstanceWhenBodyIsSame(): void
    {
        self::assertSame($this->response, $this->response->withBody($this->response->getBody()));
    }
}

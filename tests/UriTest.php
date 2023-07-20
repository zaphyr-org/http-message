<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Zaphyr\HttpMessage\Uri;

class UriTest extends TestCase
{

    /* -------------------------------------------------
     * CONSTRUCTOR
     * -------------------------------------------------
     */

    public function testGetMethodsOnConstructor(): void
    {
        $uri = new Uri('https://user:pass@local.example.com:8080/foo?bar=baz#quz');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('local.example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('user:pass@local.example.com:8080', $uri->getAuthority());
        self::assertSame('/foo', $uri->getPath());
        self::assertSame('bar=baz', $uri->getQuery());
        self::assertSame('quz', $uri->getFragment());
    }

    public function testConstructorThrowsExceptionForMalformedURI(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Uri('http:///www.foo-bar.org/');
    }

    /* -------------------------------------------------
     * SCHEME
     * -------------------------------------------------
     */

    public function testGetScheme(): void
    {
        $uri = new Uri('https://example.com');
        $this->assertEquals('https', $uri->getScheme());
    }

    public function testGetSchemeEmpty(): void
    {
        $uri = new Uri('');
        $this->assertEquals('', $uri->getScheme());
    }

    public function testWithSchemeReturnsNewInstance(): void
    {
        $uri = new Uri('https://example.com');
        $clone = $uri->withScheme('http');

        self::assertSame('http', $clone->getScheme());
        self::assertSame('http://example.com', (string) $clone);
    }

    public function testWithSchemeReturnsSameInstanceIfSchemeIsSameAsBefore(): void
    {
        $uri = new Uri('https://example.com');
        $clone = $uri->withScheme('https');

        self::assertSame($uri, $clone);
    }

    public function testWithSchemeWithEmptyString(): void
    {
        $uri = new Uri('https://example.com');
        $clone = $uri->withScheme('');

        self::assertSame('', $clone->getScheme());
    }

    public function testWithSchemeThrowsExceptionOnUnsupportedScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $uri = new Uri('https://example.com');
        $uri->withScheme('ftp');
    }

    /* -------------------------------------------------
     * GET AUTHORITY
     * -------------------------------------------------
     */

    /**
     * @param string $uri
     * @param string $expected
     *
     * @dataProvider authorityInfoDataProvider
     */
    public function testGetAuthorityReturnsExpectedValues(string $uri, string $expected): void
    {
        self::assertSame($expected, (new Uri($uri))->getAuthority());
    }

    /**
     * @param string $scheme
     * @param int    $port
     *
     * @dataProvider standardSchemePortCombinationsDataProvider
     */
    public function testGetAuthorityOmitsPortForStandardSchemePortCombinations(string $scheme, int $port): void
    {
        $uri = (new Uri())->withHost($host = 'example.com')->withScheme($scheme)->withPort($port);

        self::assertSame($host, $uri->getAuthority());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function authorityInfoDataProvider(): array
    {
        return [
            'host-only' => ['http://example.com/bar', 'example.com'],
            'host-port' => ['http://example.com:8080/bar', 'example.com:8080'],
            'host-empty' => ['example.com', ''],
            'user-host' => ['http://foo@example.com/bar', 'foo@example.com'],
            'user-host-port' => ['http://foo@example.com:8080/bar', 'foo@example.com:8080'],
        ];
    }

    /**
     * @return array<string, array<int, int|string>>
     */
    public static function standardSchemePortCombinationsDataProvider(): array
    {
        return [
            'http' => ['http', 80],
            'https' => ['https', 443],
        ];
    }

    /* -------------------------------------------------
     * USER INFO
     * -------------------------------------------------
     */

    public function testWithUserInfoReturnsNewInstanceWithUser(): void
    {
        $uri = new Uri('https://user:pass@example.com');
        $clone = $uri->withUserInfo('john');

        self::assertNotSame($uri, $clone);
        self::assertSame('john', $clone->getUserInfo());
        self::assertSame('https://john@example.com', (string)$clone);
    }

    public function testWithUserInfoReturnsNewInstanceWithUserAndPassword(): void
    {
        $uri = new Uri('https://user:pass@example.com');
        $clone = $uri->withUserInfo('john', 'secret');

        self::assertNotSame($uri, $clone);
        self::assertSame('john:secret', $clone->getUserInfo());
        self::assertSame('https://john:secret@example.com', (string)$clone);
    }

    public function testWithUserInfoReturnsSameInstanceIfUserAndPasswordAreSameAsBefore(): void
    {
        $uri = new Uri('https://user:pass@example.com');
        $clone = $uri->withUserInfo('user', 'pass');

        self::assertSame($uri, $clone);
        self::assertSame('user:pass', $clone->getUserInfo());
        self::assertSame('https://user:pass@example.com', (string)$clone);
    }

    /**
     * @param string $user
     * @param string $credential
     * @param string $expected
     *
     * @dataProvider userInfoDataProvider
     */
    public function testWithUserInfoEncodesUsernameAndPassword(string $user, string $credential, string $expected): void
    {
        $uri = new Uri('https://user:pass@example.com');
        $new = $uri->withUserInfo($user, $credential);

        self::assertSame($expected, $new->getUserInfo());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function userInfoDataProvider(): array
    {
        return [
            'valid-chars' => ['foo', 'bar', 'foo:bar'],
            'colon' => ['foo:bar', 'baz:bat', 'foo%3Abar:baz%3Abat'],
            'at' => ['user@example.com', 'cred@foo', 'user%40example.com:cred%40foo'],
            'percent' => ['%25', '%25', '%25:%25'],
            'invalid-enc' => ['%ZZ', '%GG', '%25ZZ:%25GG'],
        ];
    }

    /* -------------------------------------------------
     * HOST
     * -------------------------------------------------
     */

    public function testGetHostIsLowercase(): void
    {
        self::assertSame('example.com', (new Uri('http://EXAMPLE.COM/'))->getHost());
    }

    public function testGetHostIsLowercaseWhenIsSetWithHost(): void
    {
        $uri = (new Uri())->withHost('NEW-HOST.COM');

        self::assertSame('new-host.com', $uri->getHost());
    }

    public function testWithHostReturnsNewInstanceWithHost(): void
    {
        $uri = new Uri('https://example.com');
        $clone = $uri->withHost('zaphyr.org');

        self::assertNotSame($uri, $clone);
        self::assertSame('zaphyr.org', $clone->getHost());
        //self::assertSame('https://zaphyr.org', (string)$clone);
    }

    public function testWithHostReturnsSameInstanceWithHostIsSameAsBefore(): void
    {
        $uri = new Uri('https://example.com');
        $clone = $uri->withHost('example.com');

        self::assertSame($uri, $clone);
        self::assertSame('example.com', $clone->getHost());
        self::assertSame('https://example.com', (string)$clone);
    }

    public function testWithHostIsPrefixedByDoubleSlashIfPresent(): void
    {
        $uri = (new Uri())->withHost('example.com');

        self::assertSame('//example.com', (string)$uri);
    }

    /* -------------------------------------------------
     * PORT
     * -------------------------------------------------
     */

    /**
     * @param int|null $port
     *
     * @dataProvider validPortsDataProvider
     */
    public function testWithPortReturnsNewInstanceWithProvidedPort(int|null $port): void
    {
        $uri = new Uri('https://example.com:8080');
        $clone = $uri->withPort($port);

        self::assertNotSame($uri, $clone);
        self::assertEquals($port, $clone->getPort());
        self::assertSame(
            sprintf('https://example.com%s',  $port === null ? '' : ':' . $port),
            (string)$clone
        );
    }

    public function testWithPortReturnsSameInstanceWithProvidedPortIsSameAsBefore(): void
    {
        $uri = new Uri('https://example.com:8080');
        $clone = $uri->withPort(8080);

        self::assertSame($uri, $clone);
        self::assertSame(8080, $clone->getPort());
    }

    /**
     * @param int|null $port
     *
     * @dataProvider invalidPortsDataProvider
     */
    public function testWithPortThrowsExceptionForInvalidPorts(int|null $port): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Uri('https://example.com'))->withPort($port);
    }

    /**
     * @return array<string, array<int, int|string|null>>
     */
    public static function validPortsDataProvider(): array
    {
        return [
            'null' => [null],
            'int' => [3000],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function invalidPortsDataProvider(): array
    {
        return [
            'zero' => [0],
            'too-small' => [-1],
            'too-big' => [65536],
        ];
    }

    /* -------------------------------------------------
     * PATH
     * -------------------------------------------------
     */

    public function testGetEmptyPathOnAbsoluteUriReturnsAnEmptyPath(): void
    {
        $uri = new Uri('http://example.com/foo');
        $clone = $uri->withPath('');

        self::assertSame('', $clone->getPath());
    }

    public function testGetEmptyPathOnOriginFormRemainsAnEmptyPath(): void
    {
        self::assertSame('', (new Uri('?foo=bar'))->getPath());
    }

    public function testGetPathIsNotPrefixedWithSlashIfSetWithoutOne(): void
    {
        $uri = new Uri('http://example.com');
        $clone = $uri->withPath('foo/bar');

        self::assertSame('foo/bar', $clone->getPath());
    }

    public function testGetPathIsProperlyEncoded(): void
    {
        self::assertSame('/foo%5Ebar', ((new Uri())->withPath('/foo^bar'))->getPath());
    }

    public function testGetPathDoesNotBecomeDoubleEncoded(): void
    {
        $uri = (new Uri())->withPath($path = '/foo%5Ebar');

        self::assertSame($path, $uri->getPath());
    }

    public function testWithPathReturnsNewInstanceWithPath(): void
    {
        $uri = new Uri('https://example.com/foo');
        $clone = $uri->withPath('/bar');

        self::assertNotSame($uri, $clone);
        self::assertSame('/bar', $clone->getPath());
        self::assertSame('https://example.com/bar', (string)$clone);
    }

    public function testWithPathReturnsSameInstanceWithPathSameAsBefore(): void
    {
        $uri = new Uri('https://example.com/foo');
        $clone = $uri->withPath('/foo');

        self::assertSame($uri, $clone);
        self::assertSame('/foo', $clone->getPath());
        self::assertSame('https://example.com/foo', (string)$clone);
    }

    /**
     * @param string $path
     *
     * @dataProvider invalidPathsDataProvider
     */
    public function testWithPathThrowsExceptionForInvalidPaths(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        $uri = new Uri('https://example.com');
        $uri->withPath($path);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function invalidPathsDataProvider(): array
    {
        return [
            'query' => ['/bar/baz?bat=quz'],
            'fragment' => ['/bar/baz#bat'],
        ];
    }

    /* -------------------------------------------------
     * QUERY
     * -------------------------------------------------
     */

    public function testGetQueryStripsQueryPrefixIfPresent(): void
    {
        $uri = new Uri('http://example.com');
        $clone = $uri->withQuery('?foo=bar');

        self::assertSame('foo=bar', $clone->getQuery());
    }

    /**
     * @param string $query
     * @param string $expected
     *
     * @dataProvider queryStringsForEncodingDataProvider
     */
    public function testGetQueryIsProperlyEncoded(string $query, string $expected): void
    {
        $uri = (new Uri())->withQuery($query);

        self::assertSame($expected, $uri->getQuery());
    }

    /**
     * @param string $query
     * @param string $expected
     *
     * @dataProvider queryStringsForEncodingDataProvider
     */
    public function testGetQueryIsNotDoubleEncoded(string $query, string $expected): void
    {
        $uri = (new Uri())->withQuery($expected);

        self::assertSame($expected, $uri->getQuery());
    }

    public function testWithQueryReturnsNewInstanceWithQuery(): void
    {
        $uri = new Uri('https://example.com/foo?bar=baz');
        $clone = $uri->withQuery('baz=bat');

        self::assertNotSame($uri, $clone);
        self::assertSame('baz=bat', $clone->getQuery());
        self::assertSame('https://example.com/foo?baz=bat', (string)$clone);
    }

    public function testWithQueryReturnsSameInstanceWithQuerySameAsBefore(): void
    {
        $uri = new Uri('https://example/foo?bar=baz');
        $clone = $uri->withQuery('bar=baz');

        self::assertSame($uri, $clone);
        self::assertSame('bar=baz', $clone->getQuery());
        self::assertSame('https://example/foo?bar=baz', (string)$clone);
    }

    public function testWithQueryThrowsExceptionForInvalidQueryStrings(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $uri = new Uri('https://example.com');
        $uri->withQuery('baz=bat#quz');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function queryStringsForEncodingDataProvider(): array
    {
        return [
            'key-only' => ['k^ey', 'k%5Eey'],
            'key-value' => ['k^ey=valu`', 'k%5Eey=valu%60'],
            'array-key-only' => ['key[]', 'key%5B%5D'],
            'array-key-value' => ['key[]=valu`', 'key%5B%5D=valu%60'],
            'complex' => ['k^ey&key[]=valu`&f<>=`bar', 'k%5Eey&key%5B%5D=valu%60&f%3C%3E=%60bar'],
        ];
    }

    /* -------------------------------------------------
     * FRAGMENT
     * -------------------------------------------------
     */

    public function testGetFragmentEncodeFragmentPrefixIfPresent(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withFragment('#/foo/bar');

        self::assertSame('%23/foo/bar', $new->getFragment());
    }

    public function testGetFragmentIsProperlyEncoded(): void
    {
        $uri = (new Uri())->withFragment('/p^th?key^=`bar#b@z');
        $expected = '/p%5Eth?key%5E=%60bar%23b@z';

        self::assertSame($expected, $uri->getFragment());
    }

    public function testGetFragmentIsNotDoubleEncoded(): void
    {
        $uri = (new Uri())->withFragment($expected = '/p%5Eth?key%5E=%60bar%23b@z');

        self::assertSame($expected, $uri->getFragment());
    }

    public function testWithFragmentReturnsNewInstanceWithFragment(): void
    {
        $uri = new Uri('https://example.com/foo?bar=baz#quz');
        $clone = $uri->withFragment('qat');

        self::assertNotSame($uri, $clone);
        self::assertSame('qat', $clone->getFragment());
        self::assertSame('https://example.com/foo?bar=baz#qat', (string)$clone);
    }

    public function testWithFragmentReturnsSameInstanceWithFragmentSameAsBefore(): void
    {
        $uri = new Uri('https://example.com/foo?bar=baz#quz');
        $clone = $uri->withFragment('quz');

        self::assertSame($uri, $clone);
        self::assertSame('quz', $clone->getFragment());
        self::assertSame('https://example.com/foo?bar=baz#quz', (string)$clone);
    }

    /* -------------------------------------------------
     * TO STRING
     * -------------------------------------------------
     */

    public function testToString(): void
    {
        $uri = new Uri($url = 'https://user:pass@local.example.com:8080/foo?bar=baz#quz');

        self::assertSame($url, (string)$uri);
    }

    public function testToStringWithRelativePath(): void
    {
        $uri = new Uri($url = '/foo/bar?baz=bat');

        self::assertSame($url, (string)$uri);
    }

    public function testToStringWithAbsoluteUri(): void
    {
        $uri = new Uri($url = 'http://example.com');

        self::assertSame($url, (string)$uri);
    }

    public function testToStringWithNoPath(): void
    {
        $uri = new Uri($url = '?foo=bar');

        self::assertSame($url, (string)$uri);
    }

    public function testToStringTrimsLeadingSlashes(): void
    {
        $uri = new Uri($url = 'http://example.org//foo');

        self::assertSame('http://example.org/foo', (string)$uri);
    }

    public function testToStringPrefixedSlashDelimiter(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withPath('foo/bar');

        self::assertSame('http://example.com/foo/bar', (string)$new);
    }

    public function testToStringDistinguishZeroFromEmptyString(): void
    {
        $uri = new Uri($expected = 'https://0:0@0:1/0?0#0');

        self::assertSame($expected, (string)$uri);
    }
}

<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage\Traits;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Stream;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
trait MessageTrait
{
    /**
     * @var string[][]
     */
    private array $headers = [];

    /**
     * @var string[]
     */
    private array $headerNames = [];

    /**
     * @var string
     */
    private string $protocol = '1.1';

    /**
     * @var StreamInterface
     */
    private StreamInterface $body;

    /**
     * {@inheritdoc}
     */
    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * {@inheritdoc}
     */
    public function withProtocolVersion(string $version): MessageInterface
    {
        if ($version === '') {
            throw new InvalidArgumentException('HTTP protocol version can not be empty');
        }

        if (!preg_match('#^(1\.[01]|2(\.0)?|3(\.0)?)$#', $version)) {
            throw new InvalidArgumentException(
                'Unsupported HTTP protocol version "' . $version . '" provided'
            );
        }

        if ($this->protocol === $version) {
            return $this;
        }

        $clone = clone $this;
        $clone->protocol = $version;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader(string $name): array
    {
        if (!$this->hasHeader($name)) {
            return [];
        }

        $header = $this->headerNames[strtolower($name)];

        return $this->headers[$header];
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaderLine(string $name): string
    {
        $value = $this->getHeader($name);

        if (count($value) === 0) {
            return '';
        }

        return implode(',', $value);
    }

    /**
     * {@inheritdoc}
     */
    public function withHeader(string $name, $value): MessageInterface
    {
        $name = $this->sanitizeHeaderName($name);
        $value = $this->sanitizeHeaderValue($value);
        $normalizedName = strtolower($name);

        $clone = clone $this;

        if ($clone->hasHeader($name)) {
            unset($clone->headers[$clone->headerNames[$normalizedName]]);
        }

        $clone->headerNames[$normalizedName] = $name;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $clone = clone $this;
        $clone->setHeaders([$name => $value]);

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutHeader(string $name): MessageInterface
    {
        if (!$this->hasHeader($name)) {
            return $this;
        }

        $normalized = strtolower($name);
        $original = $this->headerNames[$normalized];

        $clone = clone $this;
        unset($clone->headers[$original], $clone->headerNames[$normalized]);

        return $clone;
    }

    /**
     * @param string $name
     *
     * @throws InvalidArgumentException
     * @return string
     */
    private function sanitizeHeaderName(string $name): string
    {
        if ($name === '' || !preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/D', $name)) {
            throw new InvalidArgumentException('Invalid header name. Must be an RFC 7230 compatible string');
        }

        return $name;
    }

    /**
     * @param string|string[] $value
     *
     * @return string[]
     */
    private function sanitizeHeaderValue(array|string $value): array
    {
        if (!is_array($value)) {
            $value = [$value];
        }

        if (count($value) === 0) {
            throw new InvalidArgumentException('Invalid header value. Must be a string or an array of strings');
        }

        $result = [];

        foreach ($value as $item) {
            if ((!is_numeric($item) && !is_string($item)) || $this->isInvalidHeaderValue((string)$item)) {
                throw new InvalidArgumentException('Invalid header value. Must be RFC 7230 compatible strings');
            }

            $item = (string)$item;
            $item = str_replace(["\r\n\t", "\r\n "], ' ', $item);
            $result[] = trim($item, "\t ");
        }

        return $result;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    private function isInvalidHeaderValue(string $value): bool
    {
        // Per RFC 7230, only VISIBLE ASCII characters, spaces, and horizontal
        // tabs are allowed in values. Header continuations MUST consist of
        // a single CRLF sequence followed by a space or horizontal tab.
        // @see http://en.wikipedia.org/wiki/HTTP_response_splitting

        // Look for:
        // \n not preceded by \r, OR
        // \r not followed by \n, OR
        // \r\n not followed by space or horizontal tab. These are all CRLF attacks
        if (preg_match("#(?:(?:(?<!\r)\n)|(?:\r(?!\n))|(?:\r\n(?![ \t])))#", $value)) {
            return true;
        }

        // Non-visible, non-whitespace characters
        // 9 === horizontal tab
        // 10 === line feed
        // 13 === carriage return
        // 32-126, 128-254 === visible
        // 127 === DEL (disallowed)
        // 255 === null byte (disallowed)
        if (preg_match('/[^\x09\x0a\x0d\x20-\x7E\x80-\xFE]/', $value)) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, string|string[]> $headers
     *
     * @return void
     */
    private function setHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $name = (string)$name;
            }

            $name = $this->sanitizeHeaderName($name);
            $value = $this->sanitizeHeaderValue($value);
            $normalizedName = strtolower($name);

            if ($this->hasHeader($normalizedName)) {
                $name = $this->headerNames[$normalizedName];
                $this->headers[$name] = array_merge($this->headers[$name], $value);
            } else {
                $this->headerNames[$normalizedName] = $name;
                $this->headers[$name] = $value;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($this->body === $body) {
            return $this;
        }

        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    /**
     * @param string|resource|StreamInterface $body
     * @param string                          $mode
     *
     * @throws InvalidArgumentException
     */
    private function setBody($body, string $mode): void
    {
        if ($body instanceof StreamInterface) {
            $this->body = $body;
        }

        if (!is_string($body) && !is_resource($body)) {
            throw new InvalidArgumentException(
                'Invalid body provided. Must be a string stream identifier, stream resource, or a ' .
                StreamInterface::class . ' implementation'
            );
        }

        $this->body = new Stream($body, $mode);
    }
}

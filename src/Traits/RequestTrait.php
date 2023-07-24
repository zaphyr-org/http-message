<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage\Traits;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Uri;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
trait RequestTrait
{
    use MessageTrait;

    /**
     * @var string
     */
    private string $method;

    /**
     * @var string|null
     */
    private string|null $requestTarget = null;

    /**
     * @var UriInterface
     */
    private UriInterface $uri;

    /**
     * @param string                          $method
     * @param string|UriInterface|null        $uri
     * @param string|StreamInterface|resource $body
     * @param array<string, string|string[]>  $headers
     * @param string                          $protocol
     *
     * @throws InvalidArgumentException
     */
    private function initialize(
        string $method = 'GET',
        string|UriInterface|null $uri = null,
        $body = 'php://memory',
        array $headers = [],
        string $protocol = '1.1'
    ): void {
        $this->setMethod($method);
        $this->setUri($uri);
        $this->setBody($body, 'wb+');
        $this->setHeaders($headers);
        $this->protocol = $protocol;
    }

    /**
     * @param string $method
     *
     * @throws InvalidArgumentException
     * @return void
     */
    private function setMethod(string $method): void
    {
        if (!preg_match('/^[!#$%&\'*+.^_`\|~0-9a-z-]+$/i', $method)) {
            throw new InvalidArgumentException('Invalid HTTP method "' . $method . '" provided');
        }

        $this->method = $method;
    }

    /**
     * @param string|UriInterface|null $uri
     *
     * @return void
     */
    private function setUri(string|UriInterface|null $uri): void
    {
        if (is_string($uri)) {
            $uri = new Uri($uri);
        } elseif ($uri === null) {
            $uri = new Uri();
        }

        $this->uri = $uri;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();

        if ($this->uri->getQuery()) {
            $target .= '?' . $this->uri->getQuery();
        }

        if (empty($target)) {
            $target = '/';
        }

        return $target;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        if (preg_match('#\s#', $requestTarget)) {
            throw new InvalidArgumentException('Invalid request target provided. Cannot contain whitespace');
        }

        $clone = clone $this;
        $clone->requestTarget = $requestTarget;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inheritdoc}
     */
    public function withMethod(string $method): RequestInterface
    {
        $clone = clone $this;
        $clone->setMethod($method);

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * {@inheritdoc}
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $clone = clone $this;
        $clone->uri = $uri;

        if ($preserveHost && $this->hasHeader('Host')) {
            return $clone;
        }

        if (!$uri->getHost()) {
            return $clone;
        }

        $host = $uri->getHost();

        if ($uri->getPort()) {
            $host .= ':' . $uri->getPort();
        }

        $clone->headerNames['host'] = 'Host';

        foreach (array_keys($clone->headers) as $header) {
            if (strtolower($header) === 'host') {
                unset($clone->headers[$header]);
            }
        }

        $clone->headers['Host'] = [$host];

        return $clone;
    }
}

<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Traits\RequestTrait;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class Request implements RequestInterface
{
    use RequestTrait;

    /**
     * @param string                          $method
     * @param string|UriInterface|null        $uri
     * @param string|StreamInterface|resource $body
     * @param array<string, string|string[]>  $headers
     * @param string                          $protocol
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        string $method = 'GET',
        string|UriInterface|null $uri = null,
        $body = 'php://temp',
        array $headers = [],
        string $protocol = '1.1'
    ) {
        $this->initialize($method, $uri, $body, $headers, $protocol);

        if (!$this->hasHeader('Host') && $this->uri->getHost()) {
            $this->headerNames['host'] = 'Host';
            $this->headers['Host'] = [$this->getHostFromUri()];
        }
    }

    /**
     * @return string
     */
    private function getHostFromUri(): string
    {
        $host = $this->uri->getHost();
        $host .= $this->uri->getPort() ? ':' . $this->uri->getPort() : '';

        return $host;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(): array
    {
        $headers = $this->headers;

        if (!$this->hasHeader('host') && $this->uri->getHost()) {
            $headers['Host'] = [$this->getHostFromUri()];
        }

        return $headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($name): array
    {
        if (!$this->hasHeader($name)) {
            if (strtolower($name) === 'host' && $this->uri->getHost()) {
                return [$this->getHostFromUri()];
            }

            return [];
        }

        $name = $this->headerNames[strtolower($name)];

        return $this->headers[$name];
    }
}

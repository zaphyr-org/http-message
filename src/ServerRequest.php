<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Traits\RequestTrait;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class ServerRequest implements ServerRequestInterface
{
    use RequestTrait;

    /**
     * @var UploadedFileInterface[]
     */
    private array $uploadedFiles;

    /**
     * @var array<string, mixed>|object|null
     */
    private $parsedBody;

    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * @param string                          $method
     * @param string|UriInterface|null        $uri
     * @param string|StreamInterface|resource $body
     * @param array<string, string|string[]>  $headers
     * @param string                          $protocol
     * @param array<string, mixed>            $serverParams
     * @param array<string, mixed>            $cookieParams
     * @param array<string, mixed>            $queryParams
     * @param UploadedFileInterface[]         $uploadedFiles
     *
     * @@throws InvalidArgumentException
     */
    public function __construct(
        string $method = 'GET',
        string|UriInterface|null $uri = null,
        $body = 'php://input',
        array $headers = [],
        string $protocol = '1.1',
        private readonly array $serverParams = [],
        private array $cookieParams = [],
        private array $queryParams = [],
        array $uploadedFiles = [],
    ) {
        $this->validateUploadedFiles($uploadedFiles);
        $this->uploadedFiles = $uploadedFiles;

        if ($body === 'php://input') {
            $body = new Stream($body, 'r');
        }

        $this->initialize($method, $uri, $body, $headers, $protocol);
    }

    /**
     * @param UploadedFileInterface[] $uploadedFiles
     *
     * @throws InvalidArgumentException
     */
    private function validateUploadedFiles(array $uploadedFiles): void
    {
        foreach ($uploadedFiles as $file) {
            if (is_array($file)) {
                $this->validateUploadedFiles($file);
                continue;
            }

            if (!$file instanceof UploadedFileInterface) {
                throw new InvalidArgumentException('Invalid upload file provided');
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $cookies
     */
    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;

        return $clone;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $query
     */
    public function withQueryParams(array $query): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->queryParams = $query;

        return $clone;
    }

    /**
     * {@inheritdoc}
     *
     * @return UploadedFileInterface[]
     */
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * {@inheritdoc}
     *
     * @param UploadedFileInterface[] $uploadedFiles
     */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $this->validateUploadedFiles($uploadedFiles);

        $clone = clone $this;
        $clone->uploadedFiles = $uploadedFiles;

        return $clone;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>|object|null
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed>|object|null $data
     */
    public function withParsedBody($data): ServerRequestInterface
    {
        if (!is_array($data) && !is_object($data) && $data !== null) {
            throw new InvalidArgumentException(
                'Invalid body data provided. Must be null, array, or object. "' . gettype($data) . '" given'
            );
        }

        $clone = clone $this;
        $clone->parsedBody = $data;

        return $clone;
    }

    /**
     * {@inheritdoc}
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutAttribute(string $name): ServerRequestInterface
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);

        return $clone;
    }
}

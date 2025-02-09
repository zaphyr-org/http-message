<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage;

use Psr\Http\Message\UriInterface;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class Uri implements UriInterface
{
    /**
     * @const array<string, int>
     */
    private const ALLOWED_SCHEMES = [
        'http' => 80,
        'https' => 443,
    ];

    /**
     * @var string
     */
    private string $scheme = '';

    /**
     * @var string
     */
    private string $userInfo = '';

    /**
     * @var string
     */
    private string $host = '';

    /**
     * @var int|null
     */
    private int|null $port = null;

    /**
     * @var string
     */
    private string $path = '';

    /**
     * @var string
     */
    private string $query = '';

    /**
     * @var string
     */
    private string $fragment = '';

    /**
     * @param string $uri
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            return;
        }

        $parts = parse_url($uri);

        if ($parts === false) {
            throw new InvalidArgumentException('The source URI string appears to be malformed');
        }

        $this->scheme = isset($parts['scheme']) ? $this->sanitizeScheme($parts['scheme']) : '';
        $this->userInfo = isset($parts['user']) ? $this->sanitizeUserInfo($parts['user']) : '';
        $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->port = $parts['port'] ?? null;
        $this->path = isset($parts['path']) ? $this->sanitizePath($parts['path']) : '';
        $this->query = isset($parts['query']) ? $this->sanitizeQuery($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? $this->sanitizeFragment($parts['fragment']) : '';

        if (isset($parts['pass'])) {
            $this->userInfo .= ':' . $parts['pass'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * {@inheritdoc}
     */
    public function withScheme(string $scheme): UriInterface
    {
        $scheme = $this->sanitizeScheme($scheme);

        if ($this->scheme === $scheme) {
            return $this;
        }

        $clone = clone $this;
        $clone->scheme = $scheme;

        return $clone;
    }

    /**
     * @param string $scheme
     *
     * @return string
     */
    private function sanitizeScheme(string $scheme): string
    {
        $scheme = str_replace('://', '', strtolower($scheme));

        if ($scheme === '') {
            return '';
        }

        if (!isset(self::ALLOWED_SCHEMES[$scheme])) {
            throw new InvalidArgumentException(
                'Invalid URI scheme "' . $scheme . '" provided. Must be one of: "' .
                implode('", "', array_keys(self::ALLOWED_SCHEMES)) . '"'
            );
        }

        return $scheme;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;

        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->isNonStandardPort($this->scheme, $this->host, $this->port)) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * @param string   $scheme
     * @param string   $host
     * @param int|null $port
     *
     * @return bool
     */
    private function isNonStandardPort(string $scheme, string $host, ?int $port): bool
    {
        if ($scheme === '') {
            return $host === '' || $port !== null;
        }

        if ($host === '' || $port === null) {
            return false;
        }

        return !isset(self::ALLOWED_SCHEMES[$scheme]) || $port !== self::ALLOWED_SCHEMES[$scheme];
    }

    /**
     * {@inheritdoc}
     */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * {@inheritdoc}
     */
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $userInfo = $this->sanitizeUserInfo($user);

        if ($password !== null) {
            $userInfo .= ':' . $this->sanitizeUserInfo($password);
        }

        if ($this->userInfo === $userInfo) {
            return $this;
        }

        $clone = clone $this;
        $clone->userInfo = $userInfo;

        return $clone;
    }

    /**
     * @param string $userInfo
     *
     * @return string
     */
    private function sanitizeUserInfo(string $userInfo): string
    {
        $match = preg_replace_callback(
            '/(?:[^%a-zA-Z0-9_\-\.~\pL!\$&\'\(\)\*\+,;=]+|%(?![A-Fa-f0-9]{2}))/u',
            static function ($match) {
                return rawurlencode($match[0]);
            },
            $userInfo
        );

        return is_string($match) ? $match : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function withHost(string $host): UriInterface
    {
        if ($this->host === $host) {
            return $this;
        }

        $clone = clone $this;
        $clone->host = strtolower($host);

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort(): ?int
    {
        return $this->isNonStandardPort($this->scheme, $this->host, $this->port) ? $this->port : null;
    }

    /**
     * {@inheritdoc}
     */
    public function withPort(?int $port): UriInterface
    {
        if ($this->port === $port) {
            return $this;
        }

        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new InvalidArgumentException(
                'Invalid URI port "' . $port . '" provided. Must be a valid TCP/UDP port'
            );
        }

        $clone = clone $this;
        $clone->port = $port;

        return $clone;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        if (str_starts_with($this->path, '/')) {
            return '/' . ltrim($this->path, '/');
        }

        return $this->path;
    }

    /**
     * {@inheritdoc}
     */
    public function withPath(string $path): UriInterface
    {
        $path = $this->sanitizePath($path);

        if ($this->path === $path) {
            return $this;
        }

        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }

    /**
     * @param string $path
     *
     * @throws InvalidArgumentException
     * @return string
     */
    private function sanitizePath(string $path): string
    {
        if (str_contains($path, '?')) {
            throw new InvalidArgumentException('Invalid URI path provided. Must not contain a query string');
        }

        if (str_contains($path, '#')) {
            throw new InvalidArgumentException('Invalid URI path provided. Must not contain a URI fragment');
        }

        $match = preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~\pL)(:@&=\+\$,\/;%]+|%(?![A-Fa-f0-9]{2}))/u',
            static function ($match) {
                return rawurlencode($match[0]);
            },
            $path
        );

        return is_string($match) ? $match : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * {@inheritdoc}
     */
    public function withQuery(string $query): UriInterface
    {
        $query = $this->sanitizeQuery($query);

        if ($this->query === $query) {
            return $this;
        }

        $clone = clone $this;
        $clone->query = $query;

        return $clone;
    }

    /**
     * @param string $query
     *
     * @throws InvalidArgumentException
     * @return string
     */
    private function sanitizeQuery(string $query): string
    {
        if (str_contains($query, '#')) {
            throw new InvalidArgumentException(
                'Invalid URI query string provided. Must not contain a URI fragment'
            );
        }

        if ($query !== '' && str_starts_with($query, '?')) {
            $query = substr($query, 1);
        }

        $parts = explode('&', $query);

        foreach ($parts as $index => $part) {
            [$key, $value] = $this->splitQueryValue($part);

            if ($value === null) {
                $parts[$index] = $this->sanitizeQueryOrFragment((string)$key);
                continue;
            }

            $parts[$index] = sprintf(
                '%s=%s',
                $this->sanitizeQueryOrFragment((string)$key),
                $this->sanitizeQueryOrFragment($value)
            );
        }

        return implode('&', $parts);
    }

    /**
     * @param string $value
     *
     * @return array<int, string|null>
     */
    private function splitQueryValue(string $value): array
    {
        $data = explode('=', $value, 2);

        if (!isset($data[1])) {
            $data[] = null;
        }

        return $data;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function sanitizeQueryOrFragment(string $value): string
    {
        $match = preg_replace_callback(
            '/(?:[^a-zA-Z0-9_\-\.~\pL!\$&\'\(\)\*\+,;=%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/u',
            static function ($match) {
                return rawurlencode($match[0]);
            },
            $value
        );

        return is_string($match) ? $match : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * {@inheritdoc}
     */
    public function withFragment(string $fragment): UriInterface
    {
        $fragment = $this->sanitizeFragment($fragment);

        if ($this->fragment === $fragment) {
            return $this;
        }

        $clone = clone $this;
        $clone->fragment = $fragment;

        return $clone;
    }

    /**
     * @param string $fragment
     *
     * @return string
     */
    private function sanitizeFragment(string $fragment): string
    {
        if ($fragment !== '' && str_starts_with($fragment, '#')) {
            $fragment = '%23' . substr($fragment, 1);
        }

        return $this->sanitizeQueryOrFragment($fragment);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        $uri = '';

        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        if ($this->getAuthority() !== '') {
            $uri .= '//' . $this->getAuthority();
        }

        $path = $this->getPath();

        if ($path !== '' && !str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $uri .= $path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }
}

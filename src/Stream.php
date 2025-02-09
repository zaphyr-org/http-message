<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage;

use Psr\Http\Message\StreamInterface;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Exceptions\RuntimeException;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class Stream implements StreamInterface
{
    /**
     * @var resource|null
     */
    private $resource;

    /**
     * @param resource|string $stream
     * @param string          $mode
     */
    public function __construct($stream, string $mode = 'r')
    {
        $error = null;
        $resource = $stream;

        if (is_string($stream)) {
            set_error_handler(
                static function (int $errno) use (&$error): bool {
                    $error = $errno;

                    return true;
                }
            );

            $resource = fopen($stream, $mode);
            restore_error_handler();
        }

        if ($error) {
            throw new RuntimeException('Invalid stream reference provided');
        }

        if (!is_resource($resource) || 'stream' !== get_resource_type($resource)) {
            throw new InvalidArgumentException(
                'Invalid stream provided. Must be a string stream identifier or stream resource'
            );
        }

        $this->resource = $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        if (!$this->isReadable()) {
            return '';
        }

        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (RuntimeException) {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if ($this->resource === null) {
            return;
        }

        /** @var resource $resource */
        $resource = $this->detach();
        fclose($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;

        return $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);

        if ($stats !== false) {
            return $stats['size'];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('No resource available. Cannot tell position');
        }

        $result = ftell($this->resource);

        if (!is_int($result)) {
            throw new RuntimeException('Error occurred during tell operation');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool
    {
        if ($this->resource === null) {
            return true;
        }

        return feof($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);

        return $meta['seekable'];
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($this->resource === null) {
            throw new RuntimeException('No resource available. Cannot tell position');
        }

        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable');
        }

        $result = fseek($this->resource, $offset, $whence);

        if ($result !== 0) {
            throw new RuntimeException('Error seeking within stream');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'];

        return str_contains($mode, 'x')
            || str_contains($mode, 'w')
            || str_contains($mode, 'c')
            || str_contains($mode, 'a')
            || str_contains($mode, '+');
    }

    /**
     * {@inheritdoc}
     */
    public function write(string $string): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('No resource available. Cannot write');
        }

        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable');
        }

        $result = fwrite($this->resource, $string);

        if ($result === false) {
            throw new RuntimeException('Error writing to stream');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        $mode = $meta['mode'];

        return (str_contains($mode, 'r') || str_contains($mode, '+'));
    }

    /**
     * {@inheritdoc}
     */
    public function read(int $length): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('No resource available. Cannot read');
        }

        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable');
        }

        /** @var int<1, max> $length */
        $result = fread($this->resource, $length);

        if ($result === false) {
            throw new RuntimeException('Error reading stream');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents(): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('No resource available. Cannot get contents');
        }

        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable');
        }

        $result = stream_get_contents($this->resource);

        if ($result === false) {
            throw new RuntimeException('Error reading stream');
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata(?string $key = null)
    {
        if ($this->resource === null) {
            throw new RuntimeException('No resource available. Cannot get metadata');
        }

        if ($key === null) {
            return stream_get_meta_data($this->resource);
        }

        $metadata = stream_get_meta_data($this->resource);

        return $metadata[$key] ?? null;
    }
}

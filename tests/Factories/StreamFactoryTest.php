<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests\Factories;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Zaphyr\HttpMessage\Exceptions\InvalidArgumentException;
use Zaphyr\HttpMessage\Factories\StreamFactory;

class StreamFactoryTest extends TestCase
{
    /* -------------------------------------------------
     * CREATE STREAM
     * -------------------------------------------------
     */

    public function testCreateStream(): void
    {
        $this->assertInstanceOf(StreamInterface::class, (new StreamFactory())->createStream());
    }

    /* -------------------------------------------------
     * CREATE STREAM FROM FILE
     * -------------------------------------------------
     */

    public function testCreateStreamFromFile(): void
    {
        $this->assertInstanceOf(
            StreamInterface::class,
            (new StreamFactory())->createStreamFromFile('php://memory')
        );
    }

    /* -------------------------------------------------
     * CREATE STREAM FROM RESOURCE
     * -------------------------------------------------
     */

    public function testCreateStreamFromResource(): void
    {
        $resource = fopen('php://memory', 'wb+');

        $this->assertInstanceOf(
            StreamInterface::class,
            (new StreamFactory())->createStreamFromResource($resource)
        );
    }

    public function testCreateStreamFromResourceThrowsExceptionWhenNoResourceGiven(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new StreamFactory())->createStreamFromResource('php://memory');
    }
}

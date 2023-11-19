<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests\Unit\Factories;

use Psr\Http\Message\UploadedFileInterface;
use Zaphyr\HttpMessage\Factories\UploadedFileFactory;
use PHPUnit\Framework\TestCase;
use Zaphyr\HTTPMessage\Stream;

class UploadedFileFactoryTest extends TestCase
{
    /* -------------------------------------------------
     * CREATE UPLOADED FILE
     * -------------------------------------------------
     */

    public function testCreateUploadedFile(): void
    {
        $stream = new Stream('php://memory', 'wb+');

        self::assertInstanceOf(
            UploadedFileInterface::class,
            (new UploadedFileFactory())->createUploadedFile($stream)
        );
    }
}

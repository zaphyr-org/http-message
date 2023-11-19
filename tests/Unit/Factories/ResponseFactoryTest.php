<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests\Unit\Factories;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zaphyr\HttpMessage\Factories\ResponseFactory;

class ResponseFactoryTest extends TestCase
{
    /* -------------------------------------------------
     * CREATE RESPONSE
     * -------------------------------------------------
     */

    public function testCreateResponse(): void
    {
        self::assertInstanceOf(ResponseInterface::class, (new ResponseFactory())->createResponse());
    }
}

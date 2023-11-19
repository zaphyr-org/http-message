<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests\Unit\Factories;

use Psr\Http\Message\ServerRequestInterface;
use Zaphyr\HttpMessage\Factories\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use Zaphyr\HttpMessage\Uri;

class ServerRequestFactoryTest extends TestCase
{
    /* -------------------------------------------------
     * CREATE SERVER REQUEST
     * -------------------------------------------------
     */

    public function testCreateServerRequest(): void
    {
        self::assertInstanceOf(
            ServerRequestInterface::class,
            (new ServerRequestFactory())->createServerRequest('GET', new Uri())
        );
    }
}

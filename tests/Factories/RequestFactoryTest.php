<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessageTests\Factories;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Zaphyr\HttpMessage\Factories\RequestFactory;

class RequestFactoryTest extends TestCase
{
    /* -------------------------------------------------
     * CREATE REQUEST
     * -------------------------------------------------
     */

    public function testCreateRequest(): void
    {
        self::assertInstanceOf(
            RequestInterface::class,
            (new RequestFactory())->createRequest('GET', 'http://example.com')
        );
    }
}

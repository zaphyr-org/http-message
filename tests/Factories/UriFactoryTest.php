<?php

declare(strict_types=1);

namespace Zaphyr\HTTPMessageTests\Factories;

use Psr\Http\Message\UriInterface;
use Zaphyr\HttpMessage\Factories\UriFactory;
use PHPUnit\Framework\TestCase;

class UriFactoryTest extends TestCase
{

    /* -------------------------------------------------
     * CREATE URI
     * -------------------------------------------------
     */

    public function testCreateUri(): void
    {
        self::assertInstanceOf(
            UriInterface::class,
            (new UriFactory())->createUri('https://example.com')
        );
    }
}

<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage\Factories;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zaphyr\HttpMessage\ServerRequest;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class ServerRequestFactory implements ServerRequestFactoryInterface
{
    /**
     * {@inheritdoc}
     *
     * @param array<string, mixed> $serverParams
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return new ServerRequest($method, $uri, serverParams: $serverParams);
    }
}

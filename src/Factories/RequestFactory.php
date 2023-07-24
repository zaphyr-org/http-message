<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage\Factories;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Zaphyr\HttpMessage\Request;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class RequestFactory implements RequestFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }
}

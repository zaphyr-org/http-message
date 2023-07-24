<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage\Factories;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Zaphyr\HttpMessage\Response;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class ResponseFactory implements ResponseFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return (new Response())->withStatus($code, $reasonPhrase);
    }
}

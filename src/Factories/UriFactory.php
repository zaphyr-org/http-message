<?php

declare(strict_types=1);

namespace Zaphyr\HttpMessage\Factories;

use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Zaphyr\HttpMessage\Uri;

/**
 * @author merloxx <merloxx@zaphyr.org>
 */
class UriFactory implements UriFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}

<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Http;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Returns the caller-supplied PSR-18 client and PSR-17 factories, or discovers
 * an installed implementation (Guzzle, Symfony, nyholm, …) via php-http/discovery.
 */
final class ClientResolver
{
    public static function client(?ClientInterface $client): ClientInterface
    {
        return $client ?? Psr18ClientDiscovery::find();
    }

    public static function requestFactory(?RequestFactoryInterface $factory): RequestFactoryInterface
    {
        return $factory ?? Psr17FactoryDiscovery::findRequestFactory();
    }

    public static function streamFactory(?StreamFactoryInterface $factory): StreamFactoryInterface
    {
        return $factory ?? Psr17FactoryDiscovery::findStreamFactory();
    }
}

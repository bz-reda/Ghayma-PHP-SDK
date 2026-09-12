<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Tests\Conformance;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Wraps a real PSR-18 client and stamps a `Prefer` header on each request so a
 * Prism mock returns the named example / status code the test asks for.
 */
final class PreferClient implements ClientInterface
{
    private ?string $prefer = null;

    public function __construct(
        private readonly ClientInterface $inner,
    ) {
    }

    /** Set the `Prefer` header for the next request(s), or null to send none. */
    public function prefer(?string $prefer): self
    {
        $this->prefer = $prefer;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->prefer !== null) {
            $request = $request->withHeader('Prefer', $this->prefer);
        }

        return $this->inner->sendRequest($request);
    }
}

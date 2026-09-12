<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client for unit tests: records every request it is handed and
 * replies with pre-queued responses (or throws a queued transport failure).
 */
final class RecordingClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|ClientExceptionInterface> */
    private array $queue = [];

    private readonly Psr17Factory $factory;

    public function __construct()
    {
        $this->factory = new Psr17Factory();
    }

    /**
     * @param array<string, mixed>|string $body
     * @param array<string, string>       $headers
     */
    public function queueJson(int $status, array|string $body, array $headers = []): self
    {
        $payload = is_string($body) ? $body : (string) json_encode($body);

        return $this->queueRaw($status, $payload, ['Content-Type' => 'application/json'] + $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function queueRaw(int $status, string $body, array $headers = []): self
    {
        $response = $this->factory->createResponse($status)
            ->withBody($this->factory->createStream($body));
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        $this->queue[] = $response;

        return $this;
    }

    public function queueThrow(ClientExceptionInterface $error): self
    {
        $this->queue[] = $error;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new TransportFailure('RecordingClient: no queued response');
        }
        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    public function lastRequest(): RequestInterface
    {
        $request = end($this->requests);
        if ($request === false) {
            throw new TransportFailure('RecordingClient: no request recorded');
        }

        return $request;
    }
}

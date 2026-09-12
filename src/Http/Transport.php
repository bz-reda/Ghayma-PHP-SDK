<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Http;

use Ghayma\Sdk\Exception\GhaymaException;
use Ghayma\Sdk\Exception\NetworkException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The shared HTTP layer for both clients: builds requests against a base URL,
 * applies default and per-call headers, sends over PSR-18, and maps non-2xx
 * responses and transport failures onto the {@see GhaymaException} hierarchy.
 */
final class Transport
{
    /**
     * @param array<string, string> $defaultHeaders headers sent on every request (e.g. the project-key bearer)
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly array $defaultHeaders = [],
    ) {
    }

    /**
     * Send a request and return the decoded JSON object ([] for 204/empty).
     *
     * @param array<string, mixed>|null $json  JSON body to encode, or null
     * @param array<string, string>     $headers per-call headers, merged over the defaults
     * @return array<string, mixed>
     */
    public function request(
        string $method,
        string $path,
        ?array $json = null,
        array $headers = [],
        ?string $rawBody = null,
        ?string $contentType = null,
    ): array {
        return $this->decodeBody($this->send($method, $path, $json, $headers, $rawBody, $contentType));
    }

    /**
     * Send a request and return the raw PSR-7 response (used for binary downloads).
     * A non-2xx status is still mapped to a {@see GhaymaException}.
     *
     * @param array<string, mixed>|null $json
     * @param array<string, string>     $headers
     */
    public function requestRaw(
        string $method,
        string $path,
        ?array $json = null,
        array $headers = [],
        ?string $rawBody = null,
        ?string $contentType = null,
    ): ResponseInterface {
        return $this->send($method, $path, $json, $headers, $rawBody, $contentType);
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string>     $headers
     */
    private function send(
        string $method,
        string $path,
        ?array $json,
        array $headers,
        ?string $rawBody,
        ?string $contentType,
    ): ResponseInterface {
        $request = $this->requestFactory
            ->createRequest($method, $this->baseUrl . $path)
            ->withHeader('Accept', 'application/json');

        foreach (array_merge($this->defaultHeaders, $headers) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($json !== null) {
            $encoded = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($encoded));
        } elseif ($rawBody !== null) {
            $request = $request->withBody($this->streamFactory->createStream($rawBody));
            if ($contentType !== null) {
                $request = $request->withHeader('Content-Type', $contentType);
            }
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException($e->getMessage(), 0, 'network_error', null, $e);
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw GhaymaException::fromResponse(
                $status,
                $this->decodeBody($response),
                $this->parseRetryAfter($response->getHeaderLine('Retry-After')),
            );
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(ResponseInterface $response): array
    {
        if ($response->getStatusCode() === 204) {
            return [];
        }

        $raw = (string) $response->getBody();
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** `Retry-After` is a delay in seconds or an HTTP-date; both normalise to whole seconds. */
    private function parseRetryAfter(string $value): ?int
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            return (int) max(0, (int) ceil((float) $raw));
        }

        $at = strtotime($raw);
        if ($at !== false) {
            return (int) max(0, $at - time());
        }

        return null;
    }
}

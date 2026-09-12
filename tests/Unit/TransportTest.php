<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Tests\Unit;

use Ghayma\Sdk\Exception\NetworkException;
use Ghayma\Sdk\Exception\RateLimitedException;
use Ghayma\Sdk\Exception\UnauthorizedException;
use Ghayma\Sdk\Http\Transport;
use Ghayma\Sdk\Tests\Support\RecordingClient;
use Ghayma\Sdk\Tests\Support\TransportFailure;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class TransportTest extends TestCase
{
    private function transport(RecordingClient $client, array $defaultHeaders = []): Transport
    {
        $factory = new Psr17Factory();

        return new Transport('https://api.ghayma.tech', $client, $factory, $factory, $defaultHeaders);
    }

    public function testPostSendsUrlMethodHeadersAndJsonBody(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['ok' => true]);
        $transport = $this->transport($client, ['Authorization' => 'Bearer gsk_test']);

        $result = $transport->request('POST', '/api/v1/storage/b1/presign/upload', ['key' => 'a.jpg', 'expiry' => 3600]);

        $this->assertSame(['ok' => true], $result);
        $request = $client->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.ghayma.tech/api/v1/storage/b1/presign/upload', (string) $request->getUri());
        $this->assertSame('Bearer gsk_test', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertSame('{"key":"a.jpg","expiry":3600}', (string) $request->getBody());
    }

    public function testPerCallHeadersMergeOverDefaultsIncludingServerKeyAndIp(): void
    {
        $client = (new RecordingClient())->queueJson(200, []);
        $transport = $this->transport($client);

        $transport->request('POST', '/login', ['email' => 'x@example.com'], [
            'X-Ghayma-Server-Key' => 'ghs_key',
            'X-Ghayma-Client-IP' => '203.0.113.10',
        ]);

        $request = $client->lastRequest();
        $this->assertSame('ghs_key', $request->getHeaderLine('X-Ghayma-Server-Key'));
        $this->assertSame('203.0.113.10', $request->getHeaderLine('X-Ghayma-Client-IP'));
    }

    public function testErrorWithCodeMapsToStatusSubclass(): void
    {
        $client = (new RecordingClient())->queueJson(429, ['error' => 'slow down', 'code' => 'rate_limited'], ['Retry-After' => '7']);
        $transport = $this->transport($client);

        try {
            $transport->request('GET', '/api/v1/storage');
            $this->fail('expected RateLimitedException');
        } catch (RateLimitedException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame('rate_limited', $e->errorCode);
            $this->assertSame(7, $e->retryAfter);
            $this->assertSame('slow down', $e->getMessage());
        }
    }

    public function testUnauthorizedWithoutCodeMapsByStatus(): void
    {
        $client = (new RecordingClient())->queueJson(401, ['error' => 'invalid key']);
        $transport = $this->transport($client);

        $this->expectException(UnauthorizedException::class);
        $transport->request('GET', '/api/v1/storage');
    }

    public function testRetryAfterHttpDateParsedToSeconds(): void
    {
        $future = gmdate('D, d M Y H:i:s \G\M\T', time() + 120);
        $client = (new RecordingClient())->queueJson(429, ['error' => 'slow'], ['Retry-After' => $future]);
        $transport = $this->transport($client);

        try {
            $transport->request('GET', '/api/v1/storage');
            $this->fail('expected RateLimitedException');
        } catch (RateLimitedException $e) {
            $this->assertGreaterThan(100, $e->retryAfter);
            $this->assertLessThanOrEqual(120, $e->retryAfter);
        }
    }

    public function testInvalidJsonErrorBodyFallsBackToDefaultMessage(): void
    {
        $client = (new RecordingClient())->queueRaw(500, '<html>oops</html>');
        $transport = $this->transport($client);

        try {
            $transport->request('GET', '/api/v1/storage');
            $this->fail('expected exception');
        } catch (\Ghayma\Sdk\Exception\GhaymaException $e) {
            $this->assertSame('Request failed with status 500', $e->getMessage());
            $this->assertSame(500, $e->status);
        }
    }

    public function testTransportFailureBecomesNetworkException(): void
    {
        $client = (new RecordingClient())->queueThrow(new TransportFailure('connection refused'));
        $transport = $this->transport($client);

        try {
            $transport->request('GET', '/api/v1/storage');
            $this->fail('expected NetworkException');
        } catch (NetworkException $e) {
            $this->assertSame(0, $e->status);
            $this->assertSame('network_error', $e->errorCode);
            $this->assertSame('connection refused', $e->getMessage());
        }
    }

    public function testNoContentReturnsEmptyArray(): void
    {
        $client = (new RecordingClient())->queueRaw(204, '');
        $transport = $this->transport($client);

        $this->assertSame([], $transport->request('DELETE', '/api/v1/storage/b1/objects', ['key' => 'a.jpg']));
    }

    public function testRequestRawReturnsResponseAndMapsErrors(): void
    {
        $client = (new RecordingClient())->queueRaw(200, 'BINARYBYTES', ['Content-Type' => 'image/jpeg', 'Content-Length' => '11']);
        $transport = $this->transport($client);

        $response = $transport->requestRaw('GET', '/api/v1/storage/b1/objects/download?key=a.jpg');
        $this->assertSame('image/jpeg', $response->getHeaderLine('Content-Type'));
        $this->assertSame('BINARYBYTES', (string) $response->getBody());
    }
}

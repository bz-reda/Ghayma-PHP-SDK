<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Tests\Unit;

use Ghayma\Sdk\GhaymaAuth;
use Ghayma\Sdk\Model\LoginSuccess;
use Ghayma\Sdk\Model\RegisterSuccess;
use Ghayma\Sdk\Model\Session;
use Ghayma\Sdk\Model\TokenPair;
use Ghayma\Sdk\Model\TotpConfirmation;
use Ghayma\Sdk\Model\TwoFaEnrollmentRequired;
use Ghayma\Sdk\Model\TwoFaRequired;
use Ghayma\Sdk\Model\User;
use Ghayma\Sdk\Model\VerificationRequired;
use Ghayma\Sdk\Tests\Support\RecordingClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class AuthClientTest extends TestCase
{
    private function auth(RecordingClient $client, ?string $serverKey = null): GhaymaAuth
    {
        $factory = new Psr17Factory();

        return new GhaymaAuth('my-app', 'https://auth.ghayma.tech', $serverKey, $client, $factory, $factory);
    }

    /** @return array<string, mixed> */
    private function sessionData(): array
    {
        return [
            'access_token' => 'eyJ...',
            'refresh_token' => '9f8e7d6c5b4a',
            'expires_in' => 900,
            'token_type' => 'Bearer',
            'user' => [
                'id' => '3f7c2b90-5e1a-4f2b-9c3d-8a1b2c3d4e5f',
                'email' => 'user@example.com',
                'name' => 'Sample User',
                'email_verified' => true,
                'provider' => 'email',
                'totp_enabled' => false,
                'whatsapp_otp_enabled' => false,
                'recovery_codes_left' => 0,
                'created_at' => '2026-09-01T10:15:00Z',
            ],
        ];
    }

    public function testLoginThreeShapes(): void
    {
        $client = (new RecordingClient())->queueJson(200, $this->sessionData());
        $success = $this->auth($client)->login('user@example.com', 's3cret');
        $r = $client->lastRequest();
        $this->assertSame('POST', $r->getMethod());
        $this->assertSame('https://auth.ghayma.tech/v1/my-app/login', (string) $r->getUri());
        $this->assertSame('{"email":"user@example.com","password":"s3cret"}', (string) $r->getBody());
        $this->assertInstanceOf(LoginSuccess::class, $success);
        $this->assertSame('eyJ...', $success->session->accessToken);

        $client = (new RecordingClient())->queueJson(200, [
            'two_fa_required' => true, 'challenge_token' => '4c1d', 'methods' => ['totp'], 'phone_hint' => '',
        ]);
        $challenge = $this->auth($client)->login('user@example.com', 's3cret');
        $this->assertInstanceOf(TwoFaRequired::class, $challenge);
        $this->assertSame('4c1d', $challenge->challengeToken);

        $client = (new RecordingClient())->queueJson(200, [
            'two_fa_enrollment_required' => true, 'enroll_token' => '7b2e', 'methods' => ['totp'],
        ]);
        $enroll = $this->auth($client)->login('user@example.com', 's3cret');
        $this->assertInstanceOf(TwoFaEnrollmentRequired::class, $enroll);
        $this->assertSame('7b2e', $enroll->enrollToken);
    }

    public function testRegisterTwoShapes(): void
    {
        $client = (new RecordingClient())->queueJson(201, $this->sessionData());
        $ok = $this->auth($client)->register('user@example.com', 's3cret-pass', 'Sample User');
        $r = $client->lastRequest();
        $this->assertSame('https://auth.ghayma.tech/v1/my-app/register', (string) $r->getUri());
        $this->assertSame('{"email":"user@example.com","password":"s3cret-pass","name":"Sample User"}', (string) $r->getBody());
        $this->assertInstanceOf(RegisterSuccess::class, $ok);

        $client = (new RecordingClient())->queueJson(201, [
            'message' => 'account created, please verify your email',
            'user' => $this->sessionData()['user'],
        ]);
        $pending = $this->auth($client)->register('user@example.com', 's3cret-pass');
        $this->assertInstanceOf(VerificationRequired::class, $pending);
        $this->assertSame('{"email":"user@example.com","password":"s3cret-pass"}', (string) $client->lastRequest()->getBody());
    }

    public function testVerify2faReturnsSessionAndStoresNothing(): void
    {
        $auth = $this->auth($client = (new RecordingClient())->queueJson(200, $this->sessionData()));
        $session = $auth->verify2fa('4c1d8ab2e3f5', '123456');
        $r = $client->lastRequest();
        $this->assertSame('https://auth.ghayma.tech/v1/my-app/2fa/verify', (string) $r->getUri());
        $this->assertSame('{"challenge_token":"4c1d8ab2e3f5","code":"123456"}', (string) $r->getBody());
        $this->assertInstanceOf(Session::class, $session);
        // Stateless: a second call carries no leftover Authorization header.
        $this->assertFalse($r->hasHeader('Authorization'));
    }

    public function testRefreshReturnsTokenPair(): void
    {
        $client = (new RecordingClient())->queueJson(200, [
            'access_token' => 'a', 'refresh_token' => 'b', 'expires_in' => 900, 'token_type' => 'Bearer',
        ]);
        $pair = $this->auth($client)->refresh('9f8e7d6c5b4a');
        $r = $client->lastRequest();
        $this->assertSame('https://auth.ghayma.tech/v1/my-app/refresh', (string) $r->getUri());
        $this->assertSame('{"refresh_token":"9f8e7d6c5b4a"}', (string) $r->getBody());
        $this->assertInstanceOf(TokenPair::class, $pair);
        $this->assertSame('b', $pair->refreshToken);
    }

    public function testGetUserSendsBearer(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['user' => $this->sessionData()['user']]);
        $user = $this->auth($client)->getUser('access-token-xyz');
        $r = $client->lastRequest();
        $this->assertSame('GET', $r->getMethod());
        $this->assertSame('https://auth.ghayma.tech/v1/my-app/me', (string) $r->getUri());
        $this->assertSame('Bearer access-token-xyz', $r->getHeaderLine('Authorization'));
        $this->assertInstanceOf(User::class, $user);
    }

    public function testServerKeyAndClientIpTravelTogether(): void
    {
        // Both present and IP well-formed → both headers sent.
        $client = (new RecordingClient())->queueJson(200, $this->sessionData());
        $this->auth($client, 'ghs_key')->login('u@example.com', 'pw', '203.0.113.10');
        $r = $client->lastRequest();
        $this->assertSame('ghs_key', $r->getHeaderLine('X-Ghayma-Server-Key'));
        $this->assertSame('203.0.113.10', $r->getHeaderLine('X-Ghayma-Client-IP'));

        // Malformed IP → neither header.
        $client = (new RecordingClient())->queueJson(200, $this->sessionData());
        $this->auth($client, 'ghs_key')->login('u@example.com', 'pw', 'unknown, 1.2.3.4');
        $r = $client->lastRequest();
        $this->assertFalse($r->hasHeader('X-Ghayma-Server-Key'));
        $this->assertFalse($r->hasHeader('X-Ghayma-Client-IP'));

        // Server key but no IP → neither.
        $client = (new RecordingClient())->queueJson(200, $this->sessionData());
        $this->auth($client, 'ghs_key')->login('u@example.com', 'pw');
        $this->assertFalse($client->lastRequest()->hasHeader('X-Ghayma-Server-Key'));

        // IP but no server key → neither.
        $client = (new RecordingClient())->queueJson(200, $this->sessionData());
        $this->auth($client)->login('u@example.com', 'pw', '203.0.113.10');
        $this->assertFalse($client->lastRequest()->hasHeader('X-Ghayma-Client-IP'));
    }

    public function testOAuthUrlBuildersAreByteForByte(): void
    {
        $auth = $this->auth(new RecordingClient());

        $this->assertSame(
            'https://auth.ghayma.tech/v1/my-app/auth/google?redirect_uri=https%3A%2F%2Fapp.example.com%2Fcb',
            $auth->googleAuthUrl('https://app.example.com/cb'),
        );

        // A redirect URI holding ~, !, (), and a space keeps those bytes (encodeURIComponent parity).
        $this->assertSame(
            'https://auth.ghayma.tech/v1/my-app/auth/github?redirect_uri=https%3A%2F%2Fapp.example.com%2Fcb%20path~a!b()',
            $auth->githubAuthUrl('https://app.example.com/cb path~a!b()'),
        );

        // With a PKCE challenge (base64url chars stay literal) the method is appended.
        $challenge = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
        $this->assertSame(
            'https://auth.ghayma.tech/v1/my-app/auth/google?redirect_uri=https%3A%2F%2Fapp.example.com%2Fcb'
            . '&code_challenge=' . $challenge . '&code_challenge_method=S256',
            $auth->googleAuthUrl('https://app.example.com/cb', $challenge),
        );
    }

    public function testExchangeCodeAndIdTokenBodies(): void
    {
        $client = (new RecordingClient())->queueJson(200, $this->sessionData());
        $this->auth($client)->exchangeCode('6d3b17f0c94a', 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk');
        $r = $client->lastRequest();
        $this->assertSame('https://auth.ghayma.tech/v1/my-app/oauth/exchange', (string) $r->getUri());
        $this->assertSame('{"code":"6d3b17f0c94a","code_verifier":"dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk"}', (string) $r->getBody());

        $client = (new RecordingClient())->queueJson(200, $this->sessionData());
        $this->auth($client)->signInWithIdToken('eyJid...', '7f3a1c9e0b52');
        $r = $client->lastRequest();
        $this->assertSame('https://auth.ghayma.tech/v1/my-app/oauth/id-token', (string) $r->getUri());
        $this->assertSame('{"provider":"google","id_token":"eyJid...","nonce":"7f3a1c9e0b52"}', (string) $r->getBody());
    }

    public function testTotpEnrollConfirmAndRecovery(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['secret' => 'JBSWY3DPEHPK3PXP', 'otpauth_uri' => 'otpauth://totp/x']);
        $this->auth($client)->enrollTotp('7b2e9c40a1d6');
        $r = $client->lastRequest();
        $this->assertSame('https://auth.ghayma.tech/v1/my-app/2fa/totp/enroll', (string) $r->getUri());
        $this->assertSame('{"enroll_token":"7b2e9c40a1d6"}', (string) $r->getBody());

        $client = (new RecordingClient())->queueJson(200, ['enabled' => true, 'recovery_codes' => ['K7T2M-9BQXR']]);
        $confirm = $this->auth($client)->confirmTotp('123456', '7b2e9c40a1d6');
        $this->assertSame('{"code":"123456","enroll_token":"7b2e9c40a1d6"}', (string) $client->lastRequest()->getBody());
        $this->assertInstanceOf(TotpConfirmation::class, $confirm);
        $this->assertNull($confirm->session);

        $client = (new RecordingClient())->queueJson(200, ['recovery_codes' => ['a', 'b', 'c']]);
        $codes = $this->auth($client)->regenerateRecoveryCodes('access-tok', 's3cret', '123456');
        $r = $client->lastRequest();
        $this->assertSame('Bearer access-tok', $r->getHeaderLine('Authorization'));
        $this->assertSame(['a', 'b', 'c'], $codes);
    }

    public function testDeleteAccountBodyOptional(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['message' => 'account deleted']);
        $this->auth($client)->deleteAccount('access-tok', 's3cret');
        $r = $client->lastRequest();
        $this->assertSame('DELETE', $r->getMethod());
        $this->assertSame('{"password":"s3cret"}', (string) $r->getBody());
        $this->assertSame('Bearer access-tok', $r->getHeaderLine('Authorization'));

        // OAuth accounts send no body.
        $client = (new RecordingClient())->queueJson(200, ['message' => 'account deleted']);
        $this->auth($client)->deleteAccount('access-tok');
        $this->assertSame('', (string) $client->lastRequest()->getBody());
    }

    public function testUpdateUserSendsOnlyProvidedFields(): void
    {
        $client = (new RecordingClient())->queueJson(200, ['user' => $this->sessionData()['user']]);
        $this->auth($client)->updateUser('access-tok', name: 'New Name', metadata: ['locale' => 'fr']);
        $r = $client->lastRequest();
        $this->assertSame('PATCH', $r->getMethod());
        $this->assertSame('{"name":"New Name","metadata":{"locale":"fr"}}', (string) $r->getBody());
    }
}

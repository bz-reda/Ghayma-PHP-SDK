<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Tests\Conformance;

use Ghayma\Sdk\Exception\ForbiddenException;
use Ghayma\Sdk\Exception\InvalidGrantException;
use Ghayma\Sdk\Exception\InvalidTokenException;
use Ghayma\Sdk\Exception\RateLimitedException;
use Ghayma\Sdk\Exception\UnauthorizedException;
use Ghayma\Sdk\GhaymaAuth;
use Ghayma\Sdk\Model\EmailChangeRequest;
use Ghayma\Sdk\Model\LoginSuccess;
use Ghayma\Sdk\Model\RegisterSuccess;
use Ghayma\Sdk\Model\ResetTokenInfo;
use Ghayma\Sdk\Model\Session;
use Ghayma\Sdk\Model\TokenPair;
use Ghayma\Sdk\Model\TotpConfirmation;
use Ghayma\Sdk\Model\TotpEnrollment;
use Ghayma\Sdk\Model\TwoFaEnrollmentRequired;
use Ghayma\Sdk\Model\TwoFaRequired;
use Ghayma\Sdk\Model\User;
use Ghayma\Sdk\Model\VerificationRequired;
use Http\Discovery\Psr18ClientDiscovery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('conformance')]
final class AuthConformanceTest extends TestCase
{
    private const TOKEN = 'access-token';

    private PreferClient $prefer;
    private GhaymaAuth $auth;

    protected function setUp(): void
    {
        $url = getenv('GHAYMA_AUTH_URL');
        if ($url === false || $url === '') {
            $this->markTestSkipped('GHAYMA_AUTH_URL not set (start Prism via tool/conformance.sh)');
        }

        $this->prefer = new PreferClient(Psr18ClientDiscovery::find());
        $this->auth = new GhaymaAuth('my-app', rtrim($url, '/'), 'ghs_key', $this->prefer);
    }

    public function testRegisterShapes(): void
    {
        $this->prefer->prefer('code=201, example=success');
        $this->assertInstanceOf(RegisterSuccess::class, $this->auth->register('user@example.com', 's3cret-pass', 'Sample User', '203.0.113.10'));

        $this->prefer->prefer('code=201, example=verification_required');
        $this->assertInstanceOf(VerificationRequired::class, $this->auth->register('user@example.com', 's3cret-pass'));
    }

    public function testLoginShapes(): void
    {
        $this->prefer->prefer('code=200, example=success');
        $this->assertInstanceOf(LoginSuccess::class, $this->auth->login('user@example.com', 's3cret', '203.0.113.10'));

        $this->prefer->prefer('code=200, example=two_fa_required');
        $this->assertInstanceOf(TwoFaRequired::class, $this->auth->login('user@example.com', 's3cret'));

        $this->prefer->prefer('code=200, example=enrollment_required');
        $this->assertInstanceOf(TwoFaEnrollmentRequired::class, $this->auth->login('user@example.com', 's3cret'));
    }

    public function testVerify2faAndRefresh(): void
    {
        $this->prefer->prefer(null);
        $this->assertInstanceOf(Session::class, $this->auth->verify2fa('4c1d8ab2e3f5', '123456'));

        $this->assertInstanceOf(TokenPair::class, $this->auth->refresh('9f8e7d6c5b4a'));
    }

    public function testRefreshErrors(): void
    {
        $this->prefer->prefer('code=401, example=invalid_token');
        try {
            $this->auth->refresh('bad');
            $this->fail('expected UnauthorizedException');
        } catch (UnauthorizedException $e) {
            $this->assertSame(401, $e->status);
        }

        $this->prefer->prefer('code=403, example=forbidden');
        $this->expectException(ForbiddenException::class);
        $this->auth->refresh('reused');
    }

    public function testMessageOnlyPublicEndpoints(): void
    {
        $this->prefer->prefer(null);
        $this->auth->logout('9f8e7d6c5b4a');
        $this->auth->forgotPassword('user@example.com', '203.0.113.10');
        $this->auth->resetPassword('5e1f0a7c9d24', 'new-s3cret-pass');
        $this->auth->resendVerification('user@example.com');
        $this->assertInstanceOf(ResetTokenInfo::class, $this->auth->verifyResetToken('5e1f0a7c9d24'));
    }

    public function testAccountEndpoints(): void
    {
        $this->prefer->prefer(null);
        $this->assertInstanceOf(User::class, $this->auth->getUser(self::TOKEN));
        $this->assertInstanceOf(User::class, $this->auth->updateUser(self::TOKEN, name: 'Sample User'));
        $this->auth->changePassword(self::TOKEN, 's3cret-passphrase', 'new-s3cret-passphrase');
        $this->assertInstanceOf(EmailChangeRequest::class, $this->auth->changeEmail(self::TOKEN, 'new@example.com', 's3cret-passphrase'));
        $this->auth->cancelEmailChange(self::TOKEN);
        $this->auth->deleteAccount(self::TOKEN, 's3cret-passphrase');
        $this->addToAssertionCount(1);
    }

    public function testTwoFactorEndpoints(): void
    {
        $this->prefer->prefer(null);
        $this->assertInstanceOf(TotpEnrollment::class, $this->auth->enrollTotp('7b2e9c40a1d6'));
        $this->assertInstanceOf(TotpConfirmation::class, $this->auth->confirmTotp('123456', '7b2e9c40a1d6'));
        $this->auth->disable2fa(self::TOKEN, 's3cret-passphrase', '123456');
        $codes = $this->auth->regenerateRecoveryCodes(self::TOKEN, 's3cret-passphrase', '123456');
        $this->assertNotEmpty($codes);
    }

    public function testOAuthExchangeAndIdToken(): void
    {
        $this->prefer->prefer(null);
        $this->assertInstanceOf(Session::class, $this->auth->exchangeCode('6d3b17f0c94a', 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk', '203.0.113.10'));
        $this->assertInstanceOf(Session::class, $this->auth->signInWithIdToken('eyJid...', '7f3a1c9e0b52', '203.0.113.10'));
    }

    public function testOAuthErrors(): void
    {
        $this->prefer->prefer('code=400, example=invalid_grant');
        try {
            $this->auth->exchangeCode('spent', 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk');
            $this->fail('expected InvalidGrantException');
        } catch (InvalidGrantException $e) {
            $this->assertSame('invalid_grant', $e->errorCode);
        }

        $this->prefer->prefer('code=401, example=invalid_token');
        try {
            $this->auth->signInWithIdToken('bad-token');
            $this->fail('expected InvalidTokenException');
        } catch (InvalidTokenException $e) {
            $this->assertSame('invalid_token', $e->errorCode);
        }
    }

    public function testRateLimited(): void
    {
        $this->prefer->prefer('code=429, example=rate_limited');
        try {
            $this->auth->login('user@example.com', 's3cret');
            $this->fail('expected RateLimitedException');
        } catch (RateLimitedException $e) {
            $this->assertSame(429, $e->status);
            $this->assertSame('rate_limited', $e->errorCode);
        }
    }
}

<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Exception;

use Ghayma\Sdk\Model\LoginResult;
use Ghayma\Sdk\Model\TwoFaEnrollmentRequired;
use Ghayma\Sdk\Model\TwoFaRequired;
use InvalidArgumentException;

/**
 * Thrown by the OAuth sign-in methods when the app's 2FA policy applies to the
 * user; no session was created. `$result` is always a {@see TwoFaRequired}
 * (finish with verify2fa()) or a {@see TwoFaEnrollmentRequired} (finish with
 * enrollTotp() + confirmTotp()), carrying the token to finish with.
 */
final class TwoFactorRequiredException extends GhaymaException
{
    /** @throws InvalidArgumentException when `$result` is not a pending second-factor step */
    public function __construct(
        public readonly LoginResult $result,
    ) {
        if (!$result instanceof TwoFaRequired && !$result instanceof TwoFaEnrollmentRequired) {
            throw new InvalidArgumentException('expected TwoFaRequired or TwoFaEnrollmentRequired, got ' . $result::class);
        }
        $enrolment = $result instanceof TwoFaEnrollmentRequired;

        parent::__construct(
            $enrolment ? 'two-factor enrolment required' : 'two-factor code required',
            200,
            $enrolment ? 'two_fa_enrollment_required' : 'two_fa_required',
        );
    }
}

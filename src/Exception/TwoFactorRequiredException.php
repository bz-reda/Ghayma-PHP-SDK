<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Exception;

use Ghayma\Sdk\Model\LoginResult;
use Ghayma\Sdk\Model\TwoFaEnrollmentRequired;
use Ghayma\Sdk\Model\TwoFaRequired;

/**
 * Thrown by the OAuth sign-in methods when the app's 2FA policy applies to the
 * user; no session was created. `$result` holds the pending step and its token:
 * a {@see TwoFaRequired} finishes with verify2fa(), a {@see TwoFaEnrollmentRequired}
 * with enrollTotp() + confirmTotp().
 */
final class TwoFactorRequiredException extends GhaymaException
{
    public function __construct(
        public readonly LoginResult $result,
    ) {
        $enrolment = $result instanceof TwoFaEnrollmentRequired;

        parent::__construct(
            $enrolment ? 'two-factor enrolment required' : 'two-factor code required',
            200,
            $enrolment ? 'two_fa_enrollment_required' : 'two_fa_required',
        );
    }
}

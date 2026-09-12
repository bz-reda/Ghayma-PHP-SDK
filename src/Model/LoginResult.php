<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/**
 * The outcome of a login: one of {@see LoginSuccess}, {@see TwoFaRequired} or
 * {@see TwoFaEnrollmentRequired}. Match with `instanceof`.
 */
interface LoginResult
{
}

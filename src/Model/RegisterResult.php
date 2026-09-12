<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/**
 * The outcome of a registration: {@see RegisterSuccess} when tokens are issued
 * immediately, or {@see VerificationRequired} when the app requires a verified
 * email first. Match with `instanceof`.
 */
interface RegisterResult
{
}

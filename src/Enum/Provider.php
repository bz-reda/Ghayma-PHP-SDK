<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Enum;

/** The identity provider an end user signed up through. */
enum Provider: string
{
    case Email = 'email';
    case Google = 'google';
    case Github = 'github';
}

<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Exception;

/** An expired or already-spent token/code (`code: invalid_grant`). */
final class InvalidGrantException extends GhaymaException
{
}

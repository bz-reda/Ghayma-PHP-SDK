<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Exception;

/** A transport-level failure before any HTTP status was seen (`status` 0). */
final class NetworkException extends GhaymaException
{
}

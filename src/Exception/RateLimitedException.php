<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Exception;

/** 429 — too many requests; `retryAfter` holds the cooldown in seconds when known. */
final class RateLimitedException extends GhaymaException
{
}

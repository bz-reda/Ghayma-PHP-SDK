<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Exception;

/** 403 — the credentials are valid but not entitled to this route or action. */
final class ForbiddenException extends GhaymaException
{
}

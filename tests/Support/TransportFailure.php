<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Tests\Support;

use Exception;
use Psr\Http\Client\ClientExceptionInterface;

/** A PSR-18 transport failure, for exercising the NetworkException path. */
final class TransportFailure extends Exception implements ClientExceptionInterface
{
}

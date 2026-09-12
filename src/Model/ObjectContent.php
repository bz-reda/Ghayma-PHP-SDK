<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

/** A downloaded object's bytes, with its content type and length. */
final readonly class ObjectContent
{
    public function __construct(
        public string $bytes,
        public string $contentType,
        public int $contentLength,
    ) {
    }
}

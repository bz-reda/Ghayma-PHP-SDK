<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Model;

use DateTimeImmutable;

/**
 * Guarded readers for turning a decoded JSON object into typed DTO fields.
 * Unknown keys are ignored; missing keys yield the type's neutral default.
 *
 * @internal
 */
trait DecodesData
{
    /** @param array<string, mixed> $d */
    private static function str(array $d, string $key): string
    {
        $v = $d[$key] ?? null;

        return is_string($v) ? $v : (is_scalar($v) ? (string) $v : '');
    }

    /** @param array<string, mixed> $d */
    private static function nstr(array $d, string $key): ?string
    {
        $v = $d[$key] ?? null;

        return is_string($v) ? $v : null;
    }

    /** @param array<string, mixed> $d */
    private static function int(array $d, string $key): int
    {
        $v = $d[$key] ?? null;

        return is_int($v) ? $v : (is_numeric($v) ? (int) $v : 0);
    }

    /** @param array<string, mixed> $d */
    private static function nint(array $d, string $key): ?int
    {
        $v = $d[$key] ?? null;

        return is_int($v) ? $v : (is_numeric($v) ? (int) $v : null);
    }

    /** @param array<string, mixed> $d */
    private static function nfloat(array $d, string $key): ?float
    {
        $v = $d[$key] ?? null;

        return is_numeric($v) ? (float) $v : null;
    }

    /** @param array<string, mixed> $d */
    private static function bool(array $d, string $key): bool
    {
        return (bool) ($d[$key] ?? false);
    }

    /** @param array<string, mixed> $d */
    private static function date(array $d, string $key): DateTimeImmutable
    {
        return new DateTimeImmutable(self::str($d, $key) ?: 'now');
    }

    /** @param array<string, mixed> $d */
    private static function ndate(array $d, string $key): ?DateTimeImmutable
    {
        $v = self::nstr($d, $key);

        return ($v === null || $v === '') ? null : new DateTimeImmutable($v);
    }

    /**
     * @param array<string, mixed> $d
     * @return list<string>
     */
    private static function strList(array $d, string $key): array
    {
        $v = $d[$key] ?? null;
        if (!is_array($v)) {
            return [];
        }

        $out = [];
        foreach ($v as $item) {
            if (is_string($item)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private static function map(array $d, string $key): array
    {
        $v = $d[$key] ?? null;

        return is_array($v) ? $v : [];
    }

    /**
     * Decode a list of nested objects with the given DTO factory.
     *
     * @template T
     * @param array<string, mixed> $d
     * @param callable(array<string, mixed>): T $factory
     * @return list<T>
     */
    private static function objectList(array $d, string $key, callable $factory): array
    {
        $v = $d[$key] ?? null;
        if (!is_array($v)) {
            return [];
        }

        $out = [];
        foreach ($v as $item) {
            if (is_array($item)) {
                /** @var array<string, mixed> $item */
                $out[] = $factory($item);
            }
        }

        return $out;
    }
}

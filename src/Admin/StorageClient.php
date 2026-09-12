<?php

declare(strict_types=1);

namespace Ghayma\Sdk\Admin;

use Ghayma\Sdk\Http\Transport;
use Ghayma\Sdk\Model\BatchDeleteResult;
use Ghayma\Sdk\Model\Bucket;
use Ghayma\Sdk\Model\BucketCredentials;
use Ghayma\Sdk\Model\DecodesData;
use Ghayma\Sdk\Model\ObjectContent;
use Ghayma\Sdk\Model\ObjectList;
use Ghayma\Sdk\Model\ObjectMetadata;
use Ghayma\Sdk\Model\PrefixDeleteResult;
use Ghayma\Sdk\Model\PresignedUrl;
use Psr\Http\Message\StreamInterface;

/**
 * Read and write the objects in a project's buckets (the `storage` capability).
 * Reachable through {@see \Ghayma\Sdk\Ghayma::$storage}.
 */
final class StorageClient
{
    use DecodesData;

    public function __construct(
        private readonly Transport $transport,
    ) {
    }

    /**
     * List the storage buckets in the key's project.
     *
     * @return list<Bucket>
     */
    public function listBuckets(): array
    {
        $res = $this->transport->request('GET', '/api/v1/storage');

        return self::objectList($res, 'buckets', Bucket::fromArray(...));
    }

    /** Get one storage bucket. */
    public function getBucket(string $id): Bucket
    {
        $res = $this->transport->request('GET', '/api/v1/storage/' . $id);

        return Bucket::fromArray(self::map($res, 'bucket'));
    }

    /** Get a bucket's S3 credentials (full read+write; requires write access). */
    public function credentials(string $id): BucketCredentials
    {
        $res = $this->transport->request('GET', '/api/v1/storage/' . $id . '/credentials');

        return BucketCredentials::fromArray(self::map($res, 'credentials'));
    }

    /** Mint a presigned URL to upload an object (default 3600s, max 604800s). */
    public function presignUpload(string $id, string $key, ?int $expiry = null): PresignedUrl
    {
        return PresignedUrl::fromArray(
            $this->transport->request('POST', '/api/v1/storage/' . $id . '/presign/upload', $this->presignBody($key, $expiry)),
        );
    }

    /** Mint a presigned URL to download an object (default 3600s, max 604800s). */
    public function presignDownload(string $id, string $key, ?int $expiry = null): PresignedUrl
    {
        return PresignedUrl::fromArray(
            $this->transport->request('POST', '/api/v1/storage/' . $id . '/presign/download', $this->presignBody($key, $expiry)),
        );
    }

    /** List objects and folders in a bucket (first page; filter with a prefix like "images/"). */
    public function listObjects(string $id, ?string $prefix = null): ObjectList
    {
        $path = '/api/v1/storage/' . $id . '/objects';
        if ($prefix !== null) {
            $path .= '?' . http_build_query(['prefix' => $prefix]);
        }

        return ObjectList::fromArray($this->transport->request('GET', $path));
    }

    /** Delete one object from a bucket. */
    public function deleteObject(string $id, string $key): void
    {
        $this->transport->request('DELETE', '/api/v1/storage/' . $id . '/objects', ['key' => $key]);
    }

    /** Upload an object under `key` (multipart). */
    public function uploadObject(string $id, string $key, string|StreamInterface $body, ?string $contentType = null): void
    {
        $bytes = is_string($body) ? $body : (string) $body;
        [$rawBody, $boundaryType] = $this->multipart($key, $bytes, $contentType ?? 'application/octet-stream');
        $this->transport->request('POST', '/api/v1/storage/' . $id . '/objects/upload', null, [], $rawBody, $boundaryType);
    }

    /** Download an object's bytes, with its content type and length. */
    public function downloadObject(string $id, string $key): ObjectContent
    {
        $response = $this->transport->requestRaw(
            'GET',
            '/api/v1/storage/' . $id . '/objects/download?' . http_build_query(['key' => $key]),
        );
        $bytes = (string) $response->getBody();
        $length = $response->getHeaderLine('Content-Length');

        return new ObjectContent(
            bytes: $bytes,
            contentType: $response->getHeaderLine('Content-Type') ?: 'application/octet-stream',
            contentLength: is_numeric($length) ? (int) $length : strlen($bytes),
        );
    }

    /** Get one object's metadata (HEAD). */
    public function objectInfo(string $id, string $key): ObjectMetadata
    {
        return ObjectMetadata::fromArray($this->transport->request(
            'GET',
            '/api/v1/storage/' . $id . '/objects/info?' . http_build_query(['key' => $key]),
        ));
    }

    /**
     * Delete up to 1000 objects by key.
     *
     * @param list<string> $keys
     */
    public function deleteBatch(string $id, array $keys): BatchDeleteResult
    {
        return BatchDeleteResult::fromArray(
            $this->transport->request('POST', '/api/v1/storage/' . $id . '/objects/delete-batch', ['keys' => $keys]),
        );
    }

    /** Delete every object under a prefix. */
    public function deletePrefix(string $id, string $prefix): PrefixDeleteResult
    {
        return PrefixDeleteResult::fromArray(
            $this->transport->request('POST', '/api/v1/storage/' . $id . '/objects/delete-prefix', ['prefix' => $prefix]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function presignBody(string $key, ?int $expiry): array
    {
        $body = ['key' => $key];
        if ($expiry !== null) {
            $body['expiry'] = $expiry;
        }

        return $body;
    }

    /**
     * Build a minimal multipart/form-data body with a `key` field and a `file` part.
     *
     * @return array{0: string, 1: string} [body, Content-Type]
     */
    private function multipart(string $key, string $fileBytes, string $fileContentType): array
    {
        $parts = explode('/', $key);
        $filename = end($parts);
        if ($filename === '') {
            $filename = 'file';
        }

        $boundary = '----GhaymaBoundary' . bin2hex(random_bytes(16));
        $eol = "\r\n";
        $body = '--' . $boundary . $eol
            . 'Content-Disposition: form-data; name="key"' . $eol . $eol
            . $key . $eol
            . '--' . $boundary . $eol
            . 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . $eol
            . 'Content-Type: ' . $fileContentType . $eol . $eol
            . $fileBytes . $eol
            . '--' . $boundary . '--' . $eol;

        return [$body, 'multipart/form-data; boundary=' . $boundary];
    }
}

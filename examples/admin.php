<?php

declare(strict_types=1);

// Admin (server-side) usage: administer an auth app's end users, read storage
// objects and database credentials with a project API key (gsk_…).
//
//   GHAYMA_API_KEY=gsk_... php examples/admin.php

require __DIR__ . '/../vendor/autoload.php';

use Ghayma\Sdk\Exception\GhaymaException;
use Ghayma\Sdk\Ghayma;

$ghayma = new Ghayma(getenv('GHAYMA_API_KEY') ?: 'gsk_your_project_key');

try {
    // Storage — upload, download, and mint a presigned URL.
    foreach ($ghayma->storage->listBuckets() as $bucket) {
        printf("bucket %s (%s)\n", $bucket->name, $bucket->id);

        $ghayma->storage->uploadObject($bucket->id, 'hello.txt', 'Hello from PHP', 'text/plain');
        $content = $ghayma->storage->downloadObject($bucket->id, 'hello.txt');
        printf("  read back %d bytes (%s)\n", $content->contentLength, $content->contentType);

        $presigned = $ghayma->storage->presignDownload($bucket->id, 'hello.txt', 3600);
        printf("  presigned: %s\n", $presigned->url);
        break;
    }

    // Databases — your site's own connection credential (the site's GHAYMA_API_KEY), for your ORM or driver.
    foreach ($ghayma->databases->list() as $db) {
        try {
            $creds = $ghayma->databases->credentials($db->id);
        } catch (GhaymaException $e) {
            // site_key_required (403): use the site's GHAYMA_API_KEY, not a project-wide key;
            // not_connected (403); no_own_credential (409): retry in a few minutes.
            printf("database %s: %s\n", $db->name, $e->errorCode);
            continue;
        }
        printf("database %s -> %s:%d/%s as %s (%s)\n", $db->name, $creds->host, $creds->port, $creds->database, $creds->username, $creds->level ?? 'n/a');
    }

    // Auth apps — page through end users and set the roles embedded in their JWT.
    foreach ($ghayma->auth->listApps() as $app) {
        $stats = $ghayma->auth->stats($app->id);
        printf("%s: %d users, %d verified\n", $app->name, $stats->totalUsers, $stats->verifiedUsers);

        $page = $ghayma->auth->listUsers($app->id, page: 1, limit: 20);
        foreach ($page->users as $user) {
            printf("  %s via %s%s\n", $user->email, $user->provider->value, $user->disabled ? ' (disabled)' : '');
        }
    }
} catch (GhaymaException $e) {
    fwrite(STDERR, sprintf("Ghayma error [%d %s]: %s\n", $e->status, $e->errorCode, $e->getMessage()));
    exit(1);
}

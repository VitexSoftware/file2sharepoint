<?php

declare(strict_types=1);

/**
 * This file is part of the file2sharepoint package
 *
 * https://github.com/VitexSoftware/file2sharepoint
 *
 * (c) Vítězslav Dvořák <info@vitexsoftware.cz>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once '../vendor/autoload.php';

use Ease\Shared;
use Office365\Runtime\Auth\UserCredentials;
use Office365\SharePoint\ClientContext;
use VitexSoftware\File2SharePoint\EntraIdAppOnlyAuthenticationContext;
use VitexSoftware\File2SharePoint\GraphSharePointClient;

\define('APP_NAME', 'file2sharepoint');

if ($argc === 1) {
    echo $argv[0]." <source/files/path/*.*> <Sharepoint/dest/folder/path/> [/path/to/config/.env] \n";
} else {
    Shared::init([
        //    'OFFICE365_USERNAME',
        //    'OFFICE365_PASSWORD',
        //        'OFFICE365_CLIENTID',
        //        'OFFICE365_CLSECRET',
        'OFFICE365_TENANT',
        'OFFICE365_SITE',
        //        'SHAREPOINT_LIBRARY',
    ], \array_key_exists(3, $argv) ? $argv[3] : '../.env');

    $path = Shared::cfg('SHAREPOINT_LIBRARY', \array_key_exists(2, $argv) ? $argv[2] : '');

    // Azure ACS (Office365\Runtime\Auth\ClientCredential + withCredentials())
    // was fully retired by Microsoft for all tenants on 2026-04-02 - it still
    // mints a syntactically valid token but SharePoint Online now rejects it
    // on the real REST call regardless of credential correctness. The
    // client-id/secret case therefore authenticates via the modern Entra ID
    // v2 client_credentials flow instead; the user-credential flow is
    // unaffected and unchanged.
    if (Shared::cfg('OFFICE365_USERNAME', false) && Shared::cfg('OFFICE365_PASSWORD', false)) {
        $credentials = new UserCredentials(Shared::cfg('OFFICE365_USERNAME'), Shared::cfg('OFFICE365_PASSWORD'));
        $ctx = (new ClientContext('https://'.Shared::cfg('OFFICE365_TENANT').'.sharepoint.com/sites/'.Shared::cfg('OFFICE365_SITE')))->withCredentials($credentials);
        $resetAuth = static function () use ($ctx, $credentials): void {
            $ctx->withCredentials($credentials);
        };
        $targetFolder = $ctx->getWeb()->getFolderByServerRelativeUrl($path);

        $doUpload = static function (string $filename, string $contents) use ($ctx, $targetFolder, $resetAuth): string {
            for ($attempt = 1; ; ++$attempt) {
                try {
                    $uploadFile = $targetFolder->uploadFile(\basename($filename), $contents);
                    $ctx->executeQuery();

                    return $ctx->getBaseUrl().'/_layouts/15/download.aspx?SourceUrl='.\urlencode($uploadFile->getServerRelativeUrl());
                } catch (\Office365\Runtime\Http\RequestException $exc) {
                    if ($attempt >= 2) {
                        throw $exc;
                    }

                    sleep(2);
                    $resetAuth();
                }
            }
        };
    } else {
        // Client-id/secret (app-only) case goes through Microsoft Graph, not
        // classic SharePoint REST (_api/web/...): that endpoint checks the
        // token's appidacr claim and requires appidacr=2 (certificate-based
        // app-only auth) - a client_credentials token obtained with a client
        // *secret* always has appidacr=1 and is unconditionally rejected with
        // "Unsupported app only token.", regardless of permissions granted.
        // Confirmed by hand: the exact same token/app/site that gets HTTP 200
        // from Microsoft Graph gets HTTP 401 from _api/web/title. See
        // https://techcommunity.microsoft.com/blog/microsoftmissioncriticalblog/avoiding-access-errors-with-sharepoint-app-only-access/4459761
        $tenant = Shared::cfg('OFFICE365_TENANT');
        $authCtx = new EntraIdAppOnlyAuthenticationContext(
            $tenant,
            Shared::cfg('OFFICE365_CLIENTID'),
            Shared::cfg('OFFICE365_CLSECRET'),
            'https://graph.microsoft.com/.default',
        );
        $graph = new GraphSharePointClient($tenant, Shared::cfg('OFFICE365_SITE'), $authCtx);

        $doUpload = static function (string $filename, string $contents) use ($graph, $path): string {
            return (string) $graph->uploadFile($path, \basename($filename), $contents)['webUrl'];
        };
    }

    $files = \glob($argv[1]);

    if ($files === false || $files === []) {
        \fwrite(\STDERR, 'No files matched: '.$argv[1].\PHP_EOL);

        exit(1);
    }

    foreach ($files as $filename) {
        $contents = \file_get_contents($filename);

        if ($contents === false) {
            \fwrite(\STDERR, 'Cannot read file: '.$filename.\PHP_EOL);

            exit(1);
        }

        try {
            $fileUrl = $doUpload($filename, $contents);
        } catch (\Throwable $exc) {
            \fwrite(\STDERR, $exc->getMessage().\PHP_EOL);

            exit(1);
        }

        echo "{$fileUrl}".\PHP_EOL;
    }
}

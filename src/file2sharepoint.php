<?php

declare(strict_types=1);

/**
 * This file is part of the CSas Statement Tools package
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
use Office365\Runtime\Auth\ClientCredential;
use Office365\Runtime\Auth\UserCredentials;
use Office365\SharePoint\ClientContext;

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

    if (Shared::cfg('OFFICE365_USERNAME', false) && Shared::cfg('OFFICE365_PASSWORD', false)) {
        $credentials = new UserCredentials(Shared::cfg('OFFICE365_USERNAME'), Shared::cfg('OFFICE365_PASSWORD'));
    } else {
        $credentials = new ClientCredential(Shared::cfg('OFFICE365_CLIENTID'), Shared::cfg('OFFICE365_CLSECRET'));
    }

    $ctx = (new ClientContext('https://'.Shared::cfg('OFFICE365_TENANT').'.sharepoint.com/sites/'.Shared::cfg('OFFICE365_SITE')))->withCredentials($credentials);

    //    $whoami = $ctx->getWeb()->getCurrentUser()->get()->executeQuery();
    //    print $whoami->getLoginName();

    $targetFolder = $ctx->getWeb()->getFolderByServerRelativeUrl(Shared::cfg('SHAREPOINT_LIBRARY', \array_key_exists(2, $argv) ? $argv[2] : ''));

    foreach (glob($argv[1]) as $filename) {
        $uploadFile = $targetFolder->uploadFile(basename($filename), file_get_contents($filename));

        try {
            $ctx->executeQuery();
        } catch (Exception $exc) {
            fwrite(fopen('php://stderr', 'wb'), $exc->getMessage().\PHP_EOL);

            exit(1);
        }

        $fileUrl = $ctx->getBaseUrl().'/_layouts/15/download.aspx?SourceUrl='.urlencode($uploadFile->getServerRelativeUrl());
        echo "{$fileUrl}".\PHP_EOL;
    }
}

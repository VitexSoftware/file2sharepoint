<?php

declare(strict_types=1);

/**
 * 
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2023 Vitex Software
 */
require_once '../vendor/autoload.php';

use Ease\Shared;
use Office365\Runtime\Auth\ClientCredential;
use Office365\Runtime\Auth\UserCredentials;
use Office365\SharePoint\ClientContext;

define('APP_NAME', 'file2sharepoint');

if ($argv == 0) {
    echo $argv[0] . ' <source/files/path/*.*> <Sharepoint/folder/path/> [/path/to/config/.env] ' . "\n";
} else {


    Shared::init([
//    'OFFICE365_USERNAME',
//    'OFFICE365_PASSWORD',
//        'OFFICE365_CLIENTID',
//        'OFFICE365_CLSECRET',
        'OFFICE365_TENANT',
        'SHAREPOINT_LIBRARY',
            ], '../.env');

    if (Shared::cfg('OFFICE365_USERNAME', false) && Shared::cfg('OFFICE365_PASSWORD', false)) {
        $credentials = new UserCredentials(Shared::cfg('OFFICE365_USERNAME'), Shared::cfg('OFFICE365_PASSWORD'));
    } else {
        $credentials = new ClientCredential(Shared::cfg('OFFICE365_CLIENTID'), Shared::cfg('OFFICE365_CLSECRET'));
    }
    $ctx = (new ClientContext('https://' . Shared::cfg('OFFICE365_TENANT') . '.sharepoint.com'))->withCredentials($credentials);
    $targetList = $ctx->getWeb()->getLists()->getByTitle(Shared::cfg('SHAREPOINT_LIBRARY', array_key_exists(2, $argv) ? $argv[2] : ''));

    foreach (glob($argv[1]) as $filename) {
        $uploadFile = $targetList->getRootFolder()->uploadFile(basename($filename), file_get_contents($filename));
        try {
            $ctx->executeQuery();
        } catch (Exception $exc) {
            fwrite(fopen('php://stderr', 'wb'), $exc->getMessage() . PHP_EOL);
            exit(1);
        }

        $fileUrl = $ctx->getBaseUrl() . '/_layouts/15/download.aspx?SourceUrl=' . urlencode($uploadFile->getServerRelativeUrl());
        print "{$fileUrl}" . PHP_EOL;
    }
}

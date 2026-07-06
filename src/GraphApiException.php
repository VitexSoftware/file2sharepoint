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

namespace VitexSoftware\File2SharePoint;

/**
 * A Microsoft Graph API call returned an error status. Mirrors
 * \Office365\Runtime\Http\RequestException's shape (getCode() = HTTP status,
 * getResponseBody() = raw response).
 */
final class GraphApiException extends \RuntimeException
{
    public function __construct(string $message, int $httpCode, private readonly ?string $responseBody = null)
    {
        parent::__construct($message, $httpCode);
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}

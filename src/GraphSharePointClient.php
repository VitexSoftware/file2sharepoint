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
 * SharePoint file upload/listing via Microsoft Graph, replacing
 * \Office365\SharePoint\ClientContext/Folder/File (the classic SharePoint
 * REST API, `_api/web/...`).
 *
 * Why not classic REST: Microsoft fully retired the ACS app-only flow on
 * 2026-04-02 (see EntraIdAppOnlyAuthenticationContext), but replacing just
 * the token source with Entra ID v2 client_credentials is not enough -
 * classic SharePoint REST checks the token's `appidacr` claim and requires
 * `appidacr=2` (certificate-based app-only auth); a client_credentials token
 * obtained with a client *secret* always has `appidacr=1` and is
 * unconditionally rejected with "Unsupported app only token." regardless of
 * permissions granted. Confirmed by hand against the daramis tenant: the
 * exact same token/app/site that gets HTTP 200 from Microsoft Graph gets
 * HTTP 401 from `_api/web/title`. See
 * https://techcommunity.microsoft.com/blog/microsoftmissioncriticalblog/avoiding-access-errors-with-sharepoint-app-only-access/4459761
 * Microsoft Graph has no such restriction and works with the existing
 * client-secret-based token, so file operations go through Graph's drive
 * API instead.
 *
 * Requires the Entra ID app registration to hold the Microsoft Graph
 * "Sites.Selected" application permission (admin-consented) and an explicit
 * grant on the target site via `POST /sites/{siteId}/permissions`.
 */
final class GraphSharePointClient
{
    private ?string $siteId = null;

    public function __construct(
        private readonly string $tenant,
        private readonly string $site,
        private readonly EntraIdAppOnlyAuthenticationContext $authContext,
    ) {}

    /**
     * Graph site ID for OFFICE365_TENANT/OFFICE365_SITE, memoized.
     */
    public function siteId(): string
    {
        if ($this->siteId !== null) {
            return $this->siteId;
        }

        $spHost = str_contains($this->tenant, '.') ? $this->tenant : $this->tenant.'.sharepoint.com';
        $body = $this->request('GET', "https://graph.microsoft.com/v1.0/sites/{$spHost}:/sites/{$this->site}");
        $decoded = json_decode($body, true);

        return $this->siteId = (string) $decoded['id'];
    }

    /**
     * Upload a file, creating/overwriting it at $path/$filename.
     *
     * @return array{webUrl: string, id: string, name: string} decoded driveItem
     */
    public function uploadFile(string $path, string $filename, string $contents): array
    {
        $url = \sprintf(
            'https://graph.microsoft.com/v1.0/sites/%s/drive/root:/%s/%s:/content',
            $this->siteId(),
            $this->encodePath($path),
            rawurlencode($filename),
        );

        return (array) json_decode($this->request('PUT', $url, $contents, 'application/octet-stream'), true);
    }

    /**
     * OFFICE365_SITE/SHAREPOINT_LIBRARY is often configured with the default
     * document library's display name as its first segment (e.g. "Sdilene
     * dokumenty/some/path" - "Sdílené dokumenty" = "Shared Documents", the
     * default library). Graph's `drive/root:/` already refers to that same
     * default library, so that segment must be stripped or Graph returns 404
     * itemNotFound - confirmed by hand against a real tenant (both cases
     * tested live: without the first segment, HTTP 200 with the expected
     * files, incl. a `webUrl` that itself contains "Sdilene%20dokumenty/...";
     * with it, HTTP 404).
     */
    private function encodePath(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        array_shift($segments);

        return implode('/', array_map('rawurlencode', $segments));
    }

    private function request(string $method, string $url, ?string $body = null, ?string $contentType = null, bool $retried = false): string
    {
        $headers = ['Authorization: Bearer '.$this->authContext->getBearerToken()];

        if ($contentType !== null) {
            $headers[] = 'Content-Type: '.$contentType;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            \CURLOPT_CUSTOMREQUEST => $method,
            \CURLOPT_HTTPHEADER => $headers,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => 30,
        ]);

        if ($body !== null) {
            curl_setopt($ch, \CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 400) {
            if (!$retried) {
                $this->authContext->forceRefresh();
                sleep(2);

                return $this->request($method, $url, $body, $contentType, true);
            }

            throw new GraphApiException(
                \is_string($response) && $response !== '' ? $response : ($curlError !== '' ? $curlError : 'Graph API request failed'),
                $httpCode,
                \is_string($response) ? $response : null,
            );
        }

        return \is_string($response) ? $response : '';
    }
}

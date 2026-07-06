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

use Office365\Runtime\Auth\IAuthenticationContext;
use Office365\Runtime\Http\RequestException;
use Office365\Runtime\Http\RequestOptions;

/**
 * SharePoint Online authentication via Entra ID (Azure AD v2) app-only
 * client_credentials, replacing the legacy SharePoint "App-Only via Azure
 * ACS" flow that Microsoft fully retired on 2026-04-02 (all tenants, no
 * extension possible - see
 * https://learn.microsoft.com/sharepoint/dev/sp-add-ins/retirement-announcement-for-azure-acs).
 *
 * \Office365\Runtime\Auth\ClientCredential + ACSTokenProvider still mint a
 * syntactically valid ACS token (HTTP 200), but SharePoint Online now rejects
 * it on the actual REST call with HTTP 401 {"error":"invalid_request"} -
 * confirmed by hand. This class instead requests a token from the Entra ID v2
 * endpoint with scope "https://{tenant}.sharepoint.com/.default", which is
 * what SharePoint Online actually honors post-retirement.
 *
 * Implements \Office365\Runtime\Auth\IAuthenticationContext directly (its
 * only method is authenticateRequest()) rather than extending the SDK's own
 * AuthenticationContext: that class's authenticateRequest() only builds the
 * Authorization header when $this->provider is an ACSTokenProvider or
 * AADTokenProvider instance, which is only ever set by its own
 * acquireAppOnlyAccessToken()/acquireTokenForClientCertificate() methods - a
 * plain setAccessToken() call would leave $provider null and hit "Unknown
 * token provider". Passing this class straight to
 * `new ClientContext($url, $authCtx)` (which accepts any
 * IAuthenticationContext) sidesteps that entirely.
 *
 * Requires an Entra ID app registration with the SharePoint "Sites.Selected"
 * application permission (Graph), admin-consented, and granted access to the
 * target site - the OFFICE365_CLIENTID/OFFICE365_CLSECRET config values must
 * point to that app, not a retired ACS "SharePoint Add-In" principal.
 */
final class EntraIdAppOnlyAuthenticationContext implements IAuthenticationContext
{
    private ?string $accessToken = null;
    private int $expiresAt = 0;

    public function __construct(
        private readonly string $tenant,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $scope,
    ) {}

    public function authenticateRequest(RequestOptions $request): void
    {
        $request->ensureHeader('Authorization', 'Bearer '.$this->getBearerToken());
    }

    /**
     * Discard the cached token so the next authenticateRequest() re-acquires one.
     */
    public function forceRefresh(): void
    {
        $this->accessToken = null;
        $this->expiresAt = 0;
    }

    /**
     * Raw access token, acquiring/refreshing it as needed. For callers that
     * talk to an API directly (e.g. Microsoft Graph via plain curl) rather
     * than through an \Office365\Runtime\Http\RequestOptions-based SDK call.
     *
     * @throws RequestException when the token endpoint doesn't return a usable token
     */
    public function getBearerToken(): string
    {
        if ($this->accessToken === null || time() >= $this->expiresAt) {
            $this->acquireToken();
        }

        return $this->accessToken;
    }

    /**
     * @throws RequestException when the token endpoint doesn't return a usable token
     */
    private function acquireToken(): void
    {
        // Accepts either a short tenant name (contoso) or a fully-qualified
        // one (contoso.onmicrosoft.com / a GUID); the v2 token endpoint needs
        // a real authority, so a bare short name gets the standard suffix.
        $authorityTenant = str_contains($this->tenant, '.') ? $this->tenant : $this->tenant.'.onmicrosoft.com';

        $ch = curl_init('https://login.microsoftonline.com/'.$authorityTenant.'/oauth2/v2.0/token');
        curl_setopt_array($ch, [
            \CURLOPT_POST => true,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            \CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => $this->scope,
            ]),
            \CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $decoded = \is_string($body) ? json_decode($body, true) : null;

        if ($httpCode !== 200 || !\is_array($decoded) || !isset($decoded['access_token'])) {
            throw new RequestException(
                \is_string($body) && $body !== '' ? $body : ($curlError !== '' ? $curlError : 'Entra ID token request returned no usable token'),
                $httpCode,
                \is_string($body) ? $body : null,
            );
        }

        $this->accessToken = $decoded['access_token'];
        // Refresh a little early rather than risk sending an expired token.
        $this->expiresAt = time() + max(60, (int) ($decoded['expires_in'] ?? 3600) - 60);
    }
}

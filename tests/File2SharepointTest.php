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

use PHPUnit\Framework\TestCase;
use VitexSoftware\File2SharePoint\EntraIdAppOnlyAuthenticationContext;
use VitexSoftware\File2SharePoint\GraphSharePointClient;

class File2SharepointTest extends TestCase
{
    private static string $script = __DIR__.'/../src/file2sharepoint.php';

    public function testScriptIsSyntacticallyValid(): void
    {
        $proc = proc_open(
            ['php', '-l', self::$script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($proc);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        self::assertSame(0, $exitCode, (string) $stdout);
    }

    public function testUsageOutputWhenCalledWithNoArguments(): void
    {
        $proc = proc_open(
            ['php', basename(self::$script)],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            \dirname(self::$script),
        );
        self::assertIsResource($proc);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('sharepoint', strtolower((string) $stdout));
    }

    public function testEnvExampleContainsAllRequiredVars(): void
    {
        $envExample = file_get_contents(__DIR__.'/../.env.example');
        self::assertIsString($envExample);

        foreach (['OFFICE365_TENANT', 'OFFICE365_SITE', 'SHAREPOINT_LIBRARY'] as $var) {
            self::assertStringContainsString($var, $envExample, ".env.example is missing {$var}");
        }
    }

    public function testCreateShareLinkReturnsPermanentWebUrl(): void
    {
        $client = $this->makeClientWithFakeResponses([
            'https://graph.microsoft.com/v1.0/sites/contoso.sharepoint.com:/sites/Team' => '{"id":"site-id"}',
            'https://graph.microsoft.com/v1.0/sites/site-id/drive/items/item-42/createLink' => '{"link":{"webUrl":"https://contoso.sharepoint.com/:w:/r/permanent-link"}}',
        ]);

        self::assertSame(
            'https://contoso.sharepoint.com/:w:/r/permanent-link',
            $client->createShareLink('item-42', 'view', 'organization'),
        );
    }

    public function testCreateShareLinkSendsRequestedTypeAndScope(): void
    {
        $capturedBody = null;

        $client = $this->makeClientWithFakeResponses(
            [
                'https://graph.microsoft.com/v1.0/sites/contoso.sharepoint.com:/sites/Team' => '{"id":"site-id"}',
                'https://graph.microsoft.com/v1.0/sites/site-id/drive/items/item-42/createLink' => '{"link":{"webUrl":"https://contoso.sharepoint.com/:w:/r/edit-link"}}',
            ],
            static function (string $method, string $url, ?string $body) use (&$capturedBody): void {
                if (str_contains($url, '/createLink')) {
                    $capturedBody = $body;
                }
            },
        );

        $client->createShareLink('item-42', 'edit', 'anonymous');

        self::assertSame(['type' => 'edit', 'scope' => 'anonymous'], json_decode((string) $capturedBody, true));
    }

    /**
     * @param array<string, string> $responsesByUrl
     */
    private function makeClientWithFakeResponses(array $responsesByUrl, ?\Closure $onRequest = null): GraphSharePointClient
    {
        $authContext = new EntraIdAppOnlyAuthenticationContext('contoso', 'client-id', 'client-secret', 'https://graph.microsoft.com/.default');
        $onRequest ??= static function (): void {};

        return new class('contoso', 'Team', $authContext, $responsesByUrl, $onRequest) extends GraphSharePointClient {
            /**
             * @param array<string, string> $responsesByUrl
             */
            public function __construct(
                string $tenant,
                string $site,
                EntraIdAppOnlyAuthenticationContext $authContext,
                private readonly array $responsesByUrl,
                private readonly ?\Closure $onRequest,
            ) {
                parent::__construct($tenant, $site, $authContext);
            }

            protected function request(string $method, string $url, ?string $body = null, ?string $contentType = null, bool $retried = false): string
            {
                ($this->onRequest)($method, $url, $body);

                if (!isset($this->responsesByUrl[$url])) {
                    throw new \RuntimeException("Unexpected request to {$url}");
                }

                return $this->responsesByUrl[$url];
            }
        };
    }
}

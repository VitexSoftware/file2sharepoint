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
}

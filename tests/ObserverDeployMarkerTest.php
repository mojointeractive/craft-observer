<?php

declare(strict_types=1);

use mojo\observer\deploy\DeployMarker;
use mojo\observer\Settings;
use PHPUnit\Framework\TestCase;

/**
 * The deploy marker names the release that went live and is signed like every observer request.
 */
final class ObserverDeployMarkerTest extends TestCase
{
    private static function settings(?string $hookUrl = 'https://x.supabase.co/functions/v1/deploy-hook'): Settings
    {
        return new Settings('observer-shared-test-vector-key-0123456789', true, 'b0000000-0000-4000-8000-000000000001', $hookUrl, null);
    }

    public function testTheMarkerCarriesTheSiteAndTheSharedVectorSignature(): void
    {
        $request = DeployMarker::request(self::settings(), '20261015-101500-abc1234', 'v5.2.0', 1792000000, '0f1e2d3c4b5a69788796a5b4c3d2e1f0');

        self::assertSame('https://x.supabase.co/functions/v1/deploy-hook', $request['url']);
        self::assertSame('{"release":"20261015-101500-abc1234","ref":"v5.2.0"}', $request['body']);
        self::assertSame('b0000000-0000-4000-8000-000000000001', $request['headers']['X-Observer-Site']);
        self::assertSame('1792000000', $request['headers']['X-Observer-Timestamp']);
        self::assertSame('0f1e2d3c4b5a69788796a5b4c3d2e1f0', $request['headers']['X-Observer-Nonce']);
        self::assertSame('ace2a8d14ea8c8a319f716c46d249535f4878a446f7410b3d1639a8bd8586b54', $request['headers']['X-Observer-Signature']);
    }

    public function testWithoutAHookUrlThereIsNothingToSend(): void
    {
        $this->expectException(LogicException::class);
        DeployMarker::request(self::settings(null), 'r', null, 1792000000, '0f1e2d3c4b5a69788796a5b4c3d2e1f0');
    }

    public function testTheReleaseIsTheDirectoryCurrentPointsAt(): void
    {
        $root = sys_get_temp_dir() . '/observer-marker-' . bin2hex(random_bytes(4));
        mkdir("$root/releases/20261015-101500-abc1234", 0777, true);
        symlink("$root/releases/20261015-101500-abc1234", "$root/current");
        try {
            self::assertSame('20261015-101500-abc1234', DeployMarker::releaseFromPath("$root/current"));
            self::assertNull(DeployMarker::releaseFromPath($root));
        } finally {
            shell_exec('rm -rf ' . escapeshellarg($root));
        }
    }
}

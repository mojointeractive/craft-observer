<?php

declare(strict_types=1);

use mojo\observer\testing\RunReport;
use PHPUnit\Framework\TestCase;

/** What a form-test run looks like to observer: no address of the client ever leaves the site. */
final class ObserverRunReportTest extends TestCase
{
    private const PROBE = 'observer+0123456789abcdef0123456789abcdef@mojo.ch';

    private static function submission(array $overrides = []): array
    {
        return $overrides + [
            'uid' => 'b1d7c1e2-0000-4000-8000-000000000001',
            'dateCreated' => '2026-10-17T07:24:00+00:00',
            'isSpam' => false,
            'spamReason' => null,
            'isIncomplete' => false,
            'values' => ['name' => 'observer test', 'email' => self::PROBE, 'privacy' => true, 'topics' => ['a', 'b'], 'empty' => null],
        ];
    }

    public function testItCountsRecipientsAndSaysWhetherTheProbeWasOne(): void
    {
        $report = RunReport::shape(self::submission(), [
            ['name' => 'Kontaktanfrage', 'subject' => 'Neue Kontaktanfrage', 'success' => true, 'message' => null,
                'to' => 'office@client.ch, boss@client.ch', 'cc' => null, 'bcc' => self::PROBE, 'dateCreated' => '2026-10-17T07:25:00+00:00'],
            ['name' => 'Bestätigung', 'subject' => 'Danke', 'success' => false, 'message' => 'No recipient email address found.',
                'to' => '', 'cc' => '', 'bcc' => '', 'dateCreated' => '2026-10-17T07:25:01+00:00'],
        ], self::PROBE, 2);

        self::assertTrue($report['found']);
        self::assertSame(2, $report['expectedNotifications']);
        self::assertSame(
            ['name' => 'Kontaktanfrage', 'subject' => 'Neue Kontaktanfrage', 'success' => true, 'message' => null,
                'toProbe' => true, 'recipients' => 3, 'dateCreated' => '2026-10-17T07:25:00+00:00'],
            $report['notifications'][0],
        );
        self::assertFalse($report['notifications'][1]['toProbe']);
        self::assertSame(0, $report['notifications'][1]['recipients']);
        self::assertSame('No recipient email address found.', $report['notifications'][1]['message']);

        $encoded = (string)json_encode($report);
        self::assertStringNotContainsString('office@client.ch', $encoded);
        self::assertStringNotContainsString('boss@client.ch', $encoded);
    }

    public function testValuesAreTextAndSpamPassesThrough(): void
    {
        $report = RunReport::shape(self::submission(['isSpam' => true, 'spamReason' => 'Contains blocked keyword']), [], self::PROBE, 1);

        self::assertSame(
            ['name' => 'observer test', 'email' => self::PROBE, 'privacy' => 'true', 'topics' => 'a, b', 'empty' => ''],
            $report['submission']['values'],
        );
        self::assertTrue($report['submission']['isSpam']);
        self::assertSame('Contains blocked keyword', $report['submission']['spamReason']);
        self::assertSame([], $report['notifications']);
    }

    public function testWithoutASubmissionTheRunIsNotFound(): void
    {
        self::assertSame(['found' => false], RunReport::notFound());
    }
}

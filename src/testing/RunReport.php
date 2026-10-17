<?php

declare(strict_types=1);

namespace mojo\observer\testing;

/**
 * The answer of `form-test-run`: what the run's submission looks like and which notifications the
 * site sent for it. Recipients are counted, never listed; only whether the probe was among them is said,
 * so no client address leaves the site.
 */
final class RunReport
{
    /** @return array{found: false} */
    public static function notFound(): array
    {
        return ['found' => false];
    }

    /**
     * @param array{uid: string, dateCreated: string, isSpam: bool, spamReason: ?string, isIncomplete: bool, values: array<string, mixed>} $submission
     * @param list<array{name: ?string, subject: ?string, success: bool, message: ?string, to: ?string, cc: ?string, bcc: ?string, dateCreated: ?string}> $notifications
     * @param int $expectedNotifications the form's enabled notifications, so observer knows when to stop waiting
     * @return array<string, mixed>
     */
    public static function shape(array $submission, array $notifications, string $probe, int $expectedNotifications): array
    {
        return [
            'found' => true,
            'submission' => [
                'uid' => $submission['uid'],
                'dateCreated' => $submission['dateCreated'],
                'isSpam' => $submission['isSpam'],
                'spamReason' => $submission['spamReason'],
                'isIncomplete' => $submission['isIncomplete'],
                'values' => array_map(self::text(...), $submission['values']),
            ],
            'expectedNotifications' => $expectedNotifications,
            'notifications' => array_map(static function (array $notification) use ($probe): array {
                $recipients = [
                    ...self::addresses($notification['to']),
                    ...self::addresses($notification['cc']),
                    ...self::addresses($notification['bcc']),
                ];

                return [
                    'name' => $notification['name'],
                    'subject' => $notification['subject'],
                    'success' => $notification['success'],
                    'message' => $notification['message'],
                    'toProbe' => in_array(strtolower($probe), array_map('strtolower', $recipients), true),
                    'recipients' => count($recipients),
                    'dateCreated' => $notification['dateCreated'],
                ];
            }, $notifications),
        ];
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => implode(', ', array_map(self::text(...), $value)),
            is_scalar($value) => (string)$value,
            $value instanceof \Stringable => (string)$value,
            default => (string)json_encode($value),
        };
    }

    /** @return list<string> "a@x.ch, B <b@y.ch>" → ["a@x.ch", "b@y.ch"] */
    private static function addresses(?string $list): array
    {
        if ($list === null || trim($list) === '') {
            return [];
        }
        $out = [];
        foreach (explode(',', $list) as $part) {
            $part = trim($part);
            if (preg_match('/<([^>]+)>/', $part, $match) === 1) {
                $part = trim($match[1]);
            }
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use mojo\observer\vitals\Provider;

/**
 * The craftly doctor's live checks (`craft craftly/project/doctor`), without the slow build check
 * and without the checks the plugin reports on every site itself (SystemProvider, QueueProvider).
 * Only on craftly sites: elsewhere the class is missing and the provider is left out.
 */
final class CraftlyDoctorProvider implements Provider
{
    /** The doctor's checks the plugin covers through its own entries: not repeated. */
    public const COVERED = ['storage', 'migrations', 'project config', 'queue'];

    /** @param string $doctorClass craftly's doctor; another name only in tests */
    public function __construct(private readonly string $doctorClass = 'modules\\craftly\\health\\Doctor')
    {
    }

    public function key(): string
    {
        return 'doctor';
    }

    public function available(): bool
    {
        return class_exists($this->doctorClass);
    }

    public function collect(): array
    {
        /** @var \modules\craftly\health\Doctor $doctor */
        $doctor = new ($this->doctorClass)();

        return ['checks' => self::withoutCovered($doctor->run('live', false))];
    }

    /**
     * @param list<array{check: string, status: string, message: string}> $checks
     * @return list<array{check: string, status: string, message: string}>
     */
    public static function withoutCovered(array $checks): array
    {
        return array_values(array_filter($checks, static fn (array $line): bool => !in_array($line['check'], self::COVERED, true)));
    }
}

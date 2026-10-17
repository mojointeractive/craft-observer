<?php

declare(strict_types=1);

namespace mojo\observer\providers;

use mojo\observer\vitals\Provider;

/**
 * The craftly doctor's live checks (`craft craftly/project/doctor`), without the slow build check.
 * Only on craftly sites: elsewhere the class is missing and the provider is left out.
 */
final class CraftlyDoctorProvider implements Provider
{
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

        return ['checks' => $doctor->run('live', false)];
    }
}

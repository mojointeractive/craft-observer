<?php

declare(strict_types=1);

namespace mojo\observer\backups;

use Closure;
use RuntimeException;
use webhubworks\backup\services\targets\LocalTarget;
use webhubworks\backup\services\targets\SftpTarget;
use webhubworks\backup\services\targets\TargetFactory;
use webhubworks\backup\services\targets\TargetInterface;

/**
 * Lists one craft-backup target for the `backups` report, on whichever version of the plugin the
 * site has:
 *
 * - 2.5 and later build targets through the plugin's TargetFactory, so a driver the site registers
 *   (`BackupRunner::EVENT_REGISTER_TARGET_DRIVERS`) is listed like the built-in ones. Earlier
 *   versions know `local` and `sftp` only, built here.
 * - 2.3.5 and later give a target the backup name, so it lists only the archives carrying it;
 *   earlier constructors take one argument and ignore the second.
 * - 2.6 and later tell a target's sanitized copies and Craft's own dumps apart from its backups.
 *   Earlier versions cannot: those lists are null, never a guess. A target written for 2.5 declares
 *   `list()` without the kind, and PHP would ignore the argument and answer with its backups.
 *
 * An SFTP target is listed with a timeout of at most SFTP_TIMEOUT seconds, so an unreachable host
 * costs seconds, not the request.
 */
final class TargetInspector
{
    public const SFTP_TIMEOUT = 10;

    /**
     * @param Closure(array<string, mixed>, string): TargetInterface $make builds a target from its definition and the backup name
     * @param array{sanitized: string, craft: string}|null $kinds the plugin's kind names, null when its targets list backups only
     */
    public function __construct(
        private readonly Closure $make,
        private readonly ?array $kinds,
    ) {
    }

    /** The inspector for the installed version of the Backup plugin. */
    public static function forInstalledPlugin(): self
    {
        $factoryClass = TargetFactory::class;
        if (class_exists($factoryClass)) {
            $factory = new $factoryClass();
            $make = static fn (array $definition, string $backupName): TargetInterface => $factory->make($definition, $backupName);
        } else {
            $make = static fn (array $definition, string $backupName): TargetInterface => self::builtIn($definition, $backupName);
        }

        $sanitized = TargetInterface::class . '::KIND_SANITIZED';
        $craft = TargetInterface::class . '::KIND_CRAFT';
        $kinds = defined($sanitized) && defined($craft)
            ? ['sanitized' => (string)constant($sanitized), 'craft' => (string)constant($craft)]
            : null;

        return new self($make, $kinds);
    }

    /**
     * @param array<string, mixed> $definition craft-backup's definition of the target
     * @return array{
     *     backups: list<array{path: string, size: int, modified: int}>,
     *     sanitized: list<array{path: string, size: int, modified: int}>|null,
     *     craft: list<array{path: string, size: int, modified: int}>|null,
     *     disk: array{total: int, free: int}|null,
     * }
     */
    public function inspect(array $definition, string $backupName): array
    {
        if (($definition['driver'] ?? null) === 'sftp') {
            // The site's own timeout when shorter; never longer than ours.
            $definition = ['timeout' => min(self::SFTP_TIMEOUT, (int)($definition['timeout'] ?? self::SFTP_TIMEOUT))] + $definition;
        }
        $target = ($this->make)($definition, $backupName);

        return [
            'backups' => array_values($target->list()),
            'sanitized' => $this->kinds === null ? null : array_values($target->list($this->kinds['sanitized'])),
            'craft' => $this->kinds === null ? null : array_values($target->list($this->kinds['craft'])),
            'disk' => $target->diskUsage(),
        ];
    }

    /**
     * The drivers craft-backup has before 2.5, which builds targets in its runner only.
     *
     * @param array<string, mixed> $definition
     */
    private static function builtIn(array $definition, string $backupName): TargetInterface
    {
        return match ($definition['driver'] ?? null) {
            'local' => new LocalTarget($definition, $backupName),
            'sftp' => new SftpTarget($definition, $backupName),
            default => throw new RuntimeException('Unknown driver ' . json_encode($definition['driver'] ?? null)),
        };
    }
}
